Vue.component('editable-list', {

    data() {
        return {

            pageData: [],
            listUpdating: false,

            dataLength: 0,

            editedRow: {},
            rowMode: 'show',

            offset: 0,
            limit: 10,
        }
    },

    props: {

        newRowTemplate: {
            type: Object,
            required: true
        },

        colsConfig: {
            type: Array,
            required: true
        },

        colsNum: {
            type: Number,
            required: true
        },

        config: {
            type: Object,
            required: true
        },

        listWidthPoints: {
            type: Number,
            required: false,
            default: 10
        },

        editedRowValidation: {
            type: Function,
            required: false,
            default: null
        }
    },

    created() {

        this.getDataLength();
        this.updateRows();
    },

    computed: {

        divWidthClass() {
            return 'col-md-' + this.listWidthPoints;
        }
    },

    methods: {

        getDataLength() {
            $.ajax({
                url: this.config['get_data_length_api'] || '',
                type: 'POST',
                dataType: 'json',
                data: {},
                success: function(response) {

                    this.dataLength = response.data;

                }.bind(this)
            });
        },

        updateRows() {
            this.listUpdating = true;
            if (this.rowMode === 'edit') {
                this.cancelEditRow();
            }

            $.ajax({
                url: this.config['update_rows_api'] || '',
                type: 'POST',
                dataType: 'json',
                data: {
                    offset: this.offset,
                    limit: this.limit,
                },
                success: function(response) {

                    this.pageData = response.data;
                    this.listUpdating = false;

                }.bind(this),
                error: function() {

                    this.listUpdating = false;
                }.bind(this)
            }); 
        },

        paginate(start, end) {

            this.offset = start;
            this.limit = end - start;
            this.updateRows();
        },

        addRow() {
            let newRow = clone(this.newRowTemplate);

            $.ajax({
                url: this.config['add_row_api'] || '',
                type: 'POST',
                dataType: 'json',
                data: {
                    row: newRow
                },
                success: function(response) {

                    if (response.status === 'error') {
                        notifyError(
                            this.config['add_row_notify_title'] || '',
                            `Произошла ошибка: ` + response.messages[0].message
                        );
                        return;
                    }
                    
                    notifySuccess(
                        this.config['add_row_notify_title'] || '',
                        `Строка успешно добавлена.`,
                        true
                    );

                    this.getDataLength();
                    this.updateRows();

                }.bind(this)
            });
        },

        editRow(index) {

            if (this.rowMode === 'edit') {
                this.cancelEditRow();
            }
            this.rowMode = 'edit';
            this.editedRow = clone(this.pageData[index]);
            this.editedRow.index = index;
        },

        saveRow() {

            //если предоставлена коллбэк функция валидации строки
            if (this.editedRowValidation !== null) {
                let validation = this.editedRowValidation(this.editedRow);

                if (!validation.result) {
                    notifyError('Ошибка валидации изменений строки', validation.error);
                    return;
                }
            }

            $.ajax({
                url: this.config['save_row_api'] || '',
                type: 'POST',
                dataType: 'json',
                data: {
                    row_id: this.editedRow.id,
                    row_data: this.editedRow,
                },
                success: function(response) {

                    if (response.status === 'error') {
                        notifyError(
                            this.config['save_row_notify_title'] || '',
                            `Произошла ошибка: ` + response.messages[0].message
                        );
                        return;
                    }
                    
                    notifySuccess(
                        this.config['save_row_notify_title'] || '',
                        `Строка успешно сохранена.`,
                        true
                    );

                    this.pageData[this.editedRow.index] = clone(this.editedRow);
                    this.editedRow = {};

                }.bind(this)
            });
        },

        cancelEditRow() {

            this.rowMode = 'show';
            this.editedRow = {};
        },

        removeRow(index) {
            let id = this.pageData[index].id;

            if (!confirm('Будет удалена строка #' + id + '.')) return;

            $.ajax({
                url: this.config['remove_row_api'] || '',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: id
                },
                success: function(response) {

                    if (response.status === 'error') {
                        notifyError(
                            this.config['remove_row_notify_title'] || '',
                            `Произошла ошибка: ` + response.messages[0].message
                        );
                        return;
                    }
                    
                    notifySuccess(
                        this.config['remove_row_notify_title'] || '',
                        `Строка успешно удалена.`,
                        true
                    );

                    this.getDataLength();
                    this.updateRows();

                }.bind(this)
            });
        },
    },

    template: '#editable-list-template'
});