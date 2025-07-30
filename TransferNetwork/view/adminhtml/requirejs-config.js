var config = {
    config: {
        mixins: {
            'Magento_Ui/js/form/form': {
                'Tirehub_TransferNetwork/js/form/form-mixin': false
            }
        },
        map: {
            '*': {
                'Tirehub_TransferNetwork/js/form/import-form': 'Tirehub_TransferNetwork/js/form/import-form',
                'Tirehub_TransferNetwork/js/grid/listing': 'Tirehub_TransferNetwork/js/grid/listing'
            }
        }
    }
};