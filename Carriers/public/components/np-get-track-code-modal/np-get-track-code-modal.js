const COD_PAYMENT_TYPE = 3;

let NPTCFormDataRef = {
    payer_type: 'Recipient',
    payment_method: 'Cash',
    cargo_type: 'Parcel',
    service_type: 'WarehouseWarehouse',
    volume_weight: '0.5',
    volume_general: '0.002',
    weight: '0.1',
    seats_amount: '1',
    cost: '300',
    desc: 'товар',
    rec_cityname: '',
    rec_area: '',
    rec_region: '',
    rec_address: '',
    rec_house: '',
    rec_flat: '',
    rec_name: '',
    rec_phone: '',
    delivery_date: '',
    cod_payer: 'Recipient',
    cod_amount: '100',
};

let NPTCValidationRules = {
    volume_weight: {
        fieldName: 'Вес объемный',
        type: 'float',
        required: true,
        max: 36,
        num_min: 0.1,
    },
    weight: {
        fieldName: 'Вес фактический',
        type: 'float',
        required: true,
        max: 36,
        num_min: 0.1,
    },
    seats_amount: {
        fieldName: 'Количество мест',
        type: 'int',
        required: true,
        max: 36,
        num_min: 1,
    },
    cost: {
        fieldName: 'Объявленная стоимость',
        required: true,
        max: 36,
    },
    desc: {
        fieldName: 'Доп. описание',
        required: false,
        max: 50,
    },
    rec_cityname: {
        fieldName: 'Город/Населенный п.',
        required: true,
        max: 36,
    },
    rec_address: {
        fieldName: 'Отделение',
        required: true,
        max: 36,
    },
    rec_house: {
        fieldName: 'Дом',
        required: false,
        max: 36,
    },
    rec_flat: {
        fieldName: 'Квартира',
        required: false,
        max: 36,
    },
    rec_name: {
        fieldName: 'ФИО получателя',
        required: true,
        max: 36,
    },
    rec_phone: {
        fieldName: 'Телефон',
        required: true,
        max: 36,
    },
    delivery_date: {
        fieldName: 'Дата отправки',
        required: true,
        max: 36,
    },
    cod_amount: {
        fieldName: 'Наложенный платеж - сумма',
        required: true,
        max: 36,
    }
};

let NPTCValidationErrors = {
    volume_weight: false,
    weight: false,
    seats_amount: false,
    cost: false,
    desc: false,
    rec_cityname: false,
    rec_address: false,
    rec_house: false,
    rec_flat: false,
    rec_name: false,
    rec_phone: false,
    delivery_date: false,
    cod_amount: false,
};


Vue.component('np-get-track-code-modal', {

    data() {
        return {

            formData: clone(NPTCFormDataRef),
            codPayment: false,
            documentType: 'WarehouseWarehouse',

            errors: clone(NPTCValidationErrors),
            errorMsgList: [],
            formSendingResult: '',
            volumeWeight: 0.5,

            payerTypesList: [],
            paymentMethodsList: [],
            cargoTypesList: [],
            serviceTypesList: [],
            codPayerTypesList: [],
            cityWarehousesList: [],

            searchDropdownData: {
                city_search: {
                    show: false,
                    selected: false,
                    search: 'citySearch',
                    values: 'recCityNames',
                    api: 'api/carriers/np/get-api-cities-list',
                    formdata: 'rec_cityname',
                },
                street_search: {
                    show: false,
                    selected: false,
                    search: 'streetSearch',
                    values: 'recAddressNames',
                    api: 'api/carriers/np/get-api-streets-list',
                    formdata: 'rec_address',
                },
            },

            citySearch: '',
            streetSearch: '',
            recCityRef: '',
            recCityNames: [],
            recAddressNames: [],

            sendingRequest: false,
            gettingTrackCode: false,
        }
    },

    props: {

        orderData: {
            type: Object,
            required: true
        },

        mode: {
            type: String,
            required: true
        },

        storedFormData: {
            type: Object,
            required: false
        },
    },

    mixins: [
        UIPermissions,
        Utils
    ],

    watch: {

        volumeWeight(v) {
            this.formData.volume_weight = v;
            this.formData.volume_general = (v / 250).toFixed(4);
        },

        documentType(v) {
            this.formData.service_type = v;
            this.cleanErrorsList();

            if (v === 'WarehouseDoors') {
                NPTCValidationRules.rec_house.required = true;
                NPTCValidationRules.rec_flat.required = true;
                NPTCValidationRules.rec_address.fieldName = 'Улица';
            } else {
                NPTCValidationRules.rec_house.required = false;
                NPTCValidationRules.rec_flat.required = false;
                NPTCValidationRules.rec_address.fieldName = 'Отделение';
                this.formData.rec_house = '';
                this.formData.rec_flat = '';
            }
        },

        orderData(v) {
            NPTCFormDataRef.cost = v.price;
            NPTCFormDataRef.cod_amount = v.cod_payment;
            NPTCFormDataRef.delivery_date = v.delivery_date;
            NPTCFormDataRef.rec_name = v.name;
            NPTCFormDataRef.rec_phone = v.phone;

            this.codPayment = v.pid == COD_PAYMENT_TYPE;
            this.formData = clone(NPTCFormDataRef);
        },

        citySearch(v) {

            this.handleSearch('city_search', v);
        },

        streetSearch(v) {

            this.handleSearch('street_search', v);
        }
    },

    created() {

    },

    methods: {

        clearCatalogsData() {
            this.payerTypesList = [];
            this.paymentMethodsList = [];
            this.cargoTypesList = [];
            this.serviceTypesList = [];
            this.codPayerTypesList = [];
        },

        getCatalogsData() {
            this.clearCatalogsData();

            $.get(
                'api/users/get-user-api-permission',
                { api_alias: 'np-get-api-catalogs-data' },
                function(response) {

                    response = JSON.parse(response);
                    if (response.data == 0) return;

                    $.ajax({
                        url: 'api/carriers/np/get-api-catalogs-data',
                        type: 'GET',
                        dataType: 'json',
                        data: {},
                        success: function (response) {

                            let data = response.data;

                            this.payerTypesList = data.payerTypes;
                            this.paymentMethodsList = data.paymentMethods;
                            this.cargoTypesList = data.cargoTypes;
                            this.serviceTypesList = data.serviceTypes;
                            this.codPayerTypesList = data.codPayerTypes;

                        }.bind(this)
                    });

                }.bind(this)
            );
        },

        sendForm() {
            if (!this.validateForm()) return;
            this.gettingTrackCode = true;

            let formData = this.addStoreDataFields(this.formData);

            $.ajax({
                url: 'api/carriers/np/get-track-code',
                type: 'POST',
                dataType: 'json',
                data: {
                    order_id: this.orderData.id,
                    form_data: formData,
                    cod_payment: this.codPayment ? 1 : 0,
                },
                success: function(response) {

                    let result = response.status;
                    this.formSendingResult = result;

                    let trackCode = response.data;
                    if (! /^\d{14}$/.test(trackCode)) {
                        this.formSendingResult = 'incorrect_track_code';
                        result = 'error';
                    }

                    //вернуть трэк-код
                    if (result === 'success') {
                        this.$emit('on-form-sended', response.data);
                    }

                    this.gettingTrackCode = false;
    
                }.bind(this),
                error: function() {

                    this.gettingTrackCode = false;

                }.bind(this)
            });
        },

        addStoreDataFields(data) {

            data.payer_type_text = this.$refs.payer_type_input.selectedOptions[0].text;
            data.payment_method_text = this.$refs.payment_method_input.selectedOptions[0].text;
            data.cargo_type_text = this.$refs.cargo_type_input.selectedOptions[0].text;
            data.doc_type_text = this.$refs.doc_type_input.selectedOptions[0].text;
            data.cp_type_text = this.$refs.cp_type_input.selectedOptions[0].text;
            data.rec_city_name_text = this.$refs.rec_cityname_input.value;

            if (this.documentType === 'WarehouseDoors') {

                data.rec_address_street_text = this.$refs.rec_address_street_input.value;
                data.rec_address_department_text = '';
            } else if (this.documentType === 'WarehouseWarehouse') {

                data.rec_address_department_text = this.$refs.rec_address_department_input.selectedOptions[0].text;
                data.rec_address_street_text = '';
            }
            return data;
        },

        validateForm() {

            let valid = true;
            let checkData = this.formData;

            this.cleanErrorsList();

            for (let field in checkData) {

                let rules = NPTCValidationRules[field] || null;
                let value = String(checkData[field]);
                let error = false;

                if (!rules) continue;

                if (rules.required && value === '') {
                    error = true;
                    this.errorMsgList.push(`Поле '${rules.fieldName}' обязательно к заполнению.`);
                }

                if (!error && rules.max && value.length > rules.max) {
                    error = true;
                    this.errorMsgList.push(
                        `Длина поля '${rules.fieldName}' превышает лимит ${rules.max} симв. (${value.length}/${rules.max})`
                    );
                }

                if (!error && rules.num_min) {
                    let converted = (rules.type === 'float') ? parseFloat(value) : parseInt(value);
                    if (converted < rules.num_min) {
                        error = true;
                        this.errorMsgList.push(
                            `Значение поля '${rules.fieldName}' ниже минимального ${rules.num_min}.`
                        );
                    }
                }

                if (!error) {
                    //sanitize string
                    checkData[field] = value.replace(/["<>:;=_$#&*~`\/|\\{}\[\]]/iug, '');
                } else {
                    this.errors[field] = true;
                    valid = false;
                }
            }

            if (valid) {
                this.formData = checkData;
            } else {
                this.formSendingResult = 'validate_errors';
            }
            return valid;
        },

        cleanErrorsList() {
            this.errorMsgList = [];
            this.formSendingResult = '';

            for (let error in this.errors) {
                this.errors[error] = false;
            }
        },

        //обработка поисковых запросов поиска города/улицы
        handleSearch(alias, v) {
            if (this.sendingRequest) return;

            if (v.length === 0) {
                this.searchDropdownData[alias].selected = false;
                this.formData[this.searchDropdownData[alias].formdata] = '';
                Vue.set(this, this.searchDropdownData[alias].values, []);

                //очистить все значения отделений и улиц если очищена строка поиска города
                if (alias === 'city_search') {
                    this.recCityRef = '';
                    this.streetSearch = '';
                    this.formData.rec_address = '';
                    this.cityWarehousesList = [];
                    this.recAddressNames = [];
                }
            }

            if (v.length > 2 && !this.searchDropdownData[alias].selected) {
                this.sendingRequest = true;

                let queryData = {
                    name: v,
                };
                if (alias === 'street_search') {
                    queryData.city_ref = this.recCityRef;
                }

                $.ajax({
                    url: this.searchDropdownData[alias].api,
                    type: 'POST',
                    dataType: 'json',
                    data: queryData,
                    success: function (response) {
                        
                        let data = response.data;
                        Vue.set(this, this.searchDropdownData[alias].values, data);
                        this.searchDropdownData[alias].show = true;

                        this.sendingRequest = false;
            
                    }.bind(this),
                    error: function() {

                        this.sendingRequest = false;

                    }.bind(this)
                });
            }
        },

        getCityWarehousesList() {
            if (this.sendingRequest) return;
            this.sendingRequest = true;

            $.ajax({
                url: 'api/carriers/np/get-api-warehouses-list',
                type: 'POST',
                dataType: 'json',
                data: {
                    city_ref: this.recCityRef,
                },
                success: function (response) {
                    
                    this.cityWarehousesList = response.data;
                    this.sendingRequest = false;
        
                }.bind(this),
                error: function() {

                    this.sendingRequest = false;

                }.bind(this)
            });
        },

        toggleSearchDropdown(alias) {
            this.searchDropdownData[alias].show = !this.searchDropdownData[alias].show;
        },

        //выбор пункта из подгружаемых списков поиска города/улицы
        selectValue(alias, name, id, value = '', addData = null) {

            Vue.set(this, this.searchDropdownData[alias].search, name);
            this.formData[this.searchDropdownData[alias].formdata] = value;

            this.searchDropdownData[alias].selected = true;
            this.toggleSearchDropdown(alias);

            if (alias === 'city_search') {
                this.recCityRef = id;

                if (addData) {
                    this.formData['rec_area'] = addData.area;
                    this.formData['rec_region'] = addData.region;
                }

                if (this.documentType === 'WarehouseWarehouse') {
                    this.getCityWarehousesList();
                }
            }
        },

        closeModal() {
            this.formData = clone(NPTCFormDataRef);

            this.cleanErrorsList();

            this.citySearch = '';
            this.streetSearch = '';
            this.recCityRef = '';
            this.recCityNames = [];
            this.recAddressNames = [];
            this.cityWarehousesList = [];
        }
    },

    template: '#np-get-track-code-modal-template',
});
