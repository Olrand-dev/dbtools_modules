const GlobalQueue = new Vue({

    el: '#global-queue',

    data: {

        tasksList: [],
        sortTypes: [],
        sortType: '',
        paginationConfig: {},
        taskTypes: [],
        tasksType: '',
        taskStatuses: [],
        tasksStatus: '',

        page: 1,
        perPage: 6,

        mode: 'show',
        editedTask: {},
        pending: false,
    },

    mixins: [
        Utils,
        UIPermissions,
    ],

    created() {

        $.ajax({
            url: 'api/global-queue/get-config-data',
            type: 'GET',
            dataType: 'json',
            data: {},
            success: function(response) {

                if (response.status === 'error') return;
                let data = response.data;

                this.taskTypes = data.task_types;
                this.tasksType = data.task_type_default;
                this.taskStatuses = data.task_statuses;
                this.tasksStatus = data.task_status_default;
                this.sortTypes = data.sort_types;
                this.sortType = data.sort_type_default;

                this.getTasksList();

            }.bind(this)
        });
    },

    methods: {

        paginate(page) {
            this.page = page;
            this.getTasksList();
        },

        getTasksList() {
            this.clearEditedData();
            this.pending = true;

            $.ajax({
                url: 'api/global-queue/get-global-tasks-list',
                type: 'GET',
                dataType: 'json',
                data: {
                    type: this.tasksType,
                    status: this.tasksStatus,
                    sort: this.sortType,
                    page: this.page,
                    per_page: this.perPage,
                },
                success: function(response) {
    
                    if (response.status === 'error') return;
                    let data = response.data; //console.log(data);
    
                    this.paginationConfig = data.pagination_config;
                    this.tasksList = data.tasks_list;
                    this.pending = false;

                }.bind(this)
            });
        },

        updateList() {
            this.getTasksList();
        },
        
        selectStatus(alias) {
            this.tasksStatus = alias;
            this.page = 1;
            this.getTasksList();
        },

        getTaskData(id) {
            return this.tasksList.filter((task) => task.id === id)[0];
        },

        editTask(id) {
            this.mode = 'edit';
            this.editedTask = clone(this.getTaskData(id));
        },

        deleteTask(id) {
            if (!confirm('Удалить задачу #' + id + '?')) return;
            if (REQUEST_BLOCK) return;
            REQUEST_BLOCK = true;

            $.ajax({
                url: 'api/global-queue/delete-global-task',
                type: 'GET',
                dataType: 'json',
                data: { id },
                success: function(response) {

                    REQUEST_BLOCK = false;
    
                    if (response.status === 'error') {
                        notifyError('Удаление задачи', 'Ошибка.');
                        return;
                    }
                    
                    notifySuccess('Удаление задачи', 'Задача успешно удалена.');
                    this.getTasksList();

                }.bind(this)
            });
        },

        saveTask() {
            if (REQUEST_BLOCK) return;
            REQUEST_BLOCK = true;

            $.ajax({
                url: 'api/global-queue/save-global-task',
                type: 'POST',
                dataType: 'json',
                data: {
                    id: this.editedTask.id,
                    data: this.editedTask,
                },
                success: function(response) {

                    REQUEST_BLOCK = false;
    
                    if (response.status === 'error') {
                        notifyError('Сохранение задачи', 'Ошибка.');
                        return;
                    }
                    
                    notifySuccess('Сохранение задачи', 'Задача успешно сохранена.');
                    this.getTasksList();

                }.bind(this)
            });
        },

        cancelEdit() {
            this.clearEditedData();
        },

        clearEditedData() {
            this.mode = 'show';
            this.editedTask = {};
        }
    }
});