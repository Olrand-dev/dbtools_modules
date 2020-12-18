<?php

declare(strict_types=1);

namespace NutixApp\SmsService\Src;


use NutixApp\Core\Src\HTTPClient;
use NutixApp\Core\Src\Db\NPDO;
use NutixApp\Core\Src\Utils\ArrayHelper;
use NutixApp\SmsService\SmsService;
use NutixApp\Users\Users;
use NutixApp\Core\Src\Utils\StringHelper;
use NutixApp\Core\Src\App;
use NutixApp\Core\Src\Exception\NutixException;

class SmsClubApi extends HTTPClient 
{

    private const URL = 'https://gate.smsclub.mobi/token/';

    public const SMS_STATUS_SENDED = 'sended';
    public const SMS_STATUS_DELIVERED = 'delivered';
    public const SMS_STATUS_EXPIRED = 'expired';
    public const SMS_STATUS_UNDELIV = 'undeliv';
    public const SMS_STATUS_REJECTED = 'rejected';

    private const SMS_STATUSES_MAP = [
        'ENROUTE' => self::SMS_STATUS_SENDED,
        'DELIVRD' => self::SMS_STATUS_DELIVERED,
        'EXPIRED' => self::SMS_STATUS_EXPIRED,
        'UNDELIV' => self::SMS_STATUS_UNDELIV,
        'REJECTD' => self::SMS_STATUS_REJECTED,
    ];

    private $userName = '380958087177';

    private $token = 'S4trtJPqL2n4uaS';

    private $from = 'VashZakaz';


    /**
     * @param string $to
     * @param string $message
     * @param array $data
     * 
     * @return string[]
     */
    public function sendSms(string $to, string $message, array $data) : array 
    {

        $out = $this->sendSingleMessage($to, $message);
        $outParts = explode('<br/>', $out);
        if (count($outParts) === 1) {
            throw new NutixException('send sms error', [
                'response' => $out,
                'to' => $to,
                'message' => $message,
                'source' => $data['source'] ?? '',
                'source_id' => $data['source_id'] ?? 0,
            ]);
        }

        $smsIds = (array) explode(';', $outParts[1]);

        foreach ($smsIds as $smsId) {

            $otherParts = [];
            if (count($smsIds) > 0) {
                $otherParts = array_values(array_diff($smsIds, [$smsId]));
            }

            NPDO::$models->sms->insert([
                'smsid' => $smsId,
                'otherparts' => json_encode($otherParts),
                'smsalias' => $data['sms_alias'] ?? '',
                'source' => $data['source'] ?? '',
                'sourceid' => $data['source_id'] ?? 0,
                'phone' => $to,
                'message' => $message,
                'status' => self::SMS_STATUS_SENDED,
            ]);
        }
        return $smsIds;
    }


    public function sendSingleMessage(string $to, string $message) : string 
    {

        $to = StringHelper::getDigits($to);
        $url = self::URL;

        $_message = urlencode(iconv('utf-8','windows-1251', $message));

        $this->url = "$url?username={$this->userName}&token={$this->token}&from={$this->from}&to=$to&text=$_message";

        $this->headers = [
            'curl' => [
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_SSL_VERIFYHOST' => false,
                'CURLOPT_SSL_VERIFYPEER' => false,
                'CURLOPT_URL' => $this->url,
            ],
        ];

        return $this->getResponse('get');
    }


    /**
     * @param array $refData
     * @param int $templateId
     */
    public function getSmsMessage(array $refData, int $templateId) : string 
    {

        $template = NPDO::$models->smsTemplates->val(
            'template',
            '`id` = ?',
            [$templateId]
        );
        $vars = [];
        preg_match_all('/\s*&([\w_]+)\s*/i', $template, $vars);
        $vars = $vars[1];
        $message = $template;

        foreach ($vars as $v) {
            $value = '';

            switch ($v) {

                case 'track_code': 
                case 'payment': {
                    $value = $refData[$v];
                    break;
                }
                case 'card_num': {
                    $value = '&' . $v;
                    break;
                }
            }

            $message = str_replace('&' . $v, $value, $message);
        }

        if (strpos($message, SmsService::SMS_VARIABLE_CARD_NUM) !== false) {

            $recipient = $refData['recipient'];
            $cardNum = NPDO::$models->ordersRecipients->val(
                'card_num',
                '`name` LIKE ?',
                [$recipient]
            );
            $msgWithCardNum = str_replace(SmsService::SMS_VARIABLE_CARD_NUM, $cardNum, $message);

            if (App::$session->userRoleAlias === Users::ROLE_MANAGER_ALIAS and !empty($cardNum)) {
                App::$session->storedSmsMessage = $msgWithCardNum;
                $message = str_replace(SmsService::SMS_VARIABLE_CARD_NUM, '****************', $message);
            } else {
                $message = $msgWithCardNum;
            }
        }
        return (string) $message;
    }


    public function getBalance() : string 
    {

        $this->url = self::URL . "getbalance.php?username={$this->userName}&token={$this->token}";
        $this->headers = [
            'curl' => [
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_URL' => $this->url,
            ],
        ];
        return $this->getResponse('get');
    }


    /**
     * @return array Список смс в которых изменился статус доставки
     */
    public function checkSmsStatuses() : array 
    {

        $sendedSms = NPDO::$models->sms->rows(
            'SELECT * FROM %table% WHERE `status` LIKE ?', [self::SMS_STATUS_SENDED]
        );
        $smsData = [];
        $smsIds = [];
        foreach ($sendedSms as $sms) {
            $smsIds[] = $sms['smsid'];
            $smsData[$sms['smsid']] = $sms;
        }
        $smsIds = implode(';', $smsIds);

        $this->url = self::URL . "state.php?username={$this->userName}&token={$this->token}&smscid=$smsIds";
        $this->headers = [
            'curl' => [
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_URL' => $this->url,
            ],
        ];
        $result = $this->getResponse('get');

        $statuses = explode('<br/>', $result);
        $statuses = ArrayHelper::filterEmptyValues((array) $statuses);
        array_shift($statuses);
        array_pop($statuses);
        $changed = [];
        foreach ($statuses as $statusData) {

            $statParts = explode(':', $statusData);
            $smsId = $statParts[0];
            $status = self::SMS_STATUSES_MAP[trim($statParts[1])];

            if ($status !== self::SMS_STATUS_SENDED) {
                $smsData[$smsId]['status'] = $status;
                $changed[$smsId] = $smsData[$smsId];

                NPDO::$models->sms->execute(
                    'UPDATE %table% SET `status` = ? WHERE `smsid` LIKE ?',
                    [$status, $smsId]
                );
            }
        }
        return $changed;
    }

}