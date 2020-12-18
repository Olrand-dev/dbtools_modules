Vue.component('sms-modal', {

    template: '#sms-modal-template',

    data() {
        return {
            mode: 'show',
            balance: 0,

            templateId: 0,
            templates: [],

            message: ''
        }
    },

    props: ['ref-data', 'add-data'],

    mixins: [
        UIPermissions
    ],

    created() {

        $.get(
            'api/users/get-user-api-permission', 
            { api_alias: 'get-sms-templates-list' }, 
            function(response) {

                response = JSON.parse(response);
                if (response.data == 0) return;

                $.ajax({
                    url: 'api/sms-service/get-sms-templates-list',
                    type: 'GET',
                    dataType: 'json',
                    data: {},
                    success: function(response) {
            
                        if (response.status === 'error') return;
                        this.templates = response.data;
        
                    }.bind(this)
                });

            }.bind(this)
        );
    },

    methods: {

        edit() {
            this.mode = 'edit';
        },

        closeModal() {
            this.mode = 'show';
            this.templateId = 0;
            this.message = '';
        },

        getBalance() {
            this.balance = 0;

            $.get(
                'api/users/get-user-api-permission', 
                { api_alias: 'get-sms-balance' }, 
                function(response) {
    
                    response = JSON.parse(response);
                    if (response.data == 0) return;
    
                    $.get('api/sms-service/get-sms-balance', function(response) {
                        response = JSON.parse(response);
        
                        if (response.status === 'error') return;
                        this.balance = response.data;
                        
                    }.bind(this));
    
                }.bind(this)
            );
        },

        getMessage() {

            $.ajax({
                url: 'api/sms-service/get-sms-message',
                type: 'POST',
                dataType: 'json',
                data: {
                    template_id: this.templateId,
                    ref_data: this.refData
                },
                success: function(response) {
        
                    if (response.status === 'error') return;
                    this.message = response.data;
                    this.mode = 'show';
    
                }.bind(this)
            });
        },

        saveEditorContent(text) {

            this.message = text;
            this.mode = 'show';
        },

        send() {
            if (this.message === '' || this.templateId === 0) {
                alert('Выберите шаблон сообщения перед отправкой.');
                return;
            }
            this.mode = 'show';

            $.ajax({
                url: 'api/sms-service/send-sms',
                type: 'POST',
                dataType: 'json',
                data: {	
                    phone: this.refData.phone,	
                    template_id: this.templateId,			
                    message: this.message,
                    add_data: this.addData					
                },
                success: function(response) {
        
                    if (response.status === 'error') {

                        alert('При отправке смс произошла ошибка.');
                        return;
                    }
                    alert('Сообщение смс отправлено.');
                    this.$emit('on-sms-sended', response.data);

                    this.getBalance();

                }.bind(this)
            });
        }
    }
});