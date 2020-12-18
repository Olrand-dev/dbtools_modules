Vue.component('sms-templates-list', {

    template: '#sms-templates-list-template',

    data() {
        return {

            config: {
                'list_title': 'Шаблоны смс сообщений',
                'empty_list_msg': 'Добавьте первый шаблон.',
                'add_row_api': 'api/sms-service/add-sms-template',
                'add_row_notify_title': 'Добавление шаблона',
                'remove_row_api': 'api/sms-service/delete-sms-template',
                'remove_row_notify_title': 'Удаление шаблона',
                'save_row_api': 'api/sms-service/save-sms-template',
                'save_row_notify_title': 'Сохранение шаблона',
                'update_rows_api': 'api/sms-service/get-sms-templates-page',
                'get_data_length_api': 'api/sms-service/get-sms-templates-length',
            },
    
            newRowTemplate: {
                name: 'new-template',
                template: ''
            },
    
            colsConfig: [
                {
                    model: 'id',
                    title: '#',
                    type: 'static',
                    editable: false
                },
                {
                    model: 'name',
                    title: 'Название',
                    type: 'text',
                    editable: true
                },
                {
                    model: 'alias',
                    title: 'Алиас',
                    type: 'static',
                    editable: false
                },
                {
                    model: 'template',
                    title: 'Шаблон',
                    type: 'text',
                    editable: true
                }
            ],
    
            colsNum: 5
        }
    }
});