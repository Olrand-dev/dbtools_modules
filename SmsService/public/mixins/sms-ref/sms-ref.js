const SmsRef = {

    data() {
        return {

            defaultRef: {
                phone: '',
                track_code: '',
                payment: '',
                recipient: '',
            },
            smsRefData: {}
        }
    },

    mixins: [
        Utils
    ],

    methods: {

        setSmsRef(data = {}) {
            let ref = clone(this.defaultRef);

            for (let item in data) {
                ref[item] = data[item];
            }
            this.smsRefData = ref;
        }
    }
};