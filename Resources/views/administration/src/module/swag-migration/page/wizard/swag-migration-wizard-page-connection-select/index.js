import { Component, State } from 'src/core/shopware';
import template from './swag-migration-wizard-page-connection-select.html.twig';

Component.register('swag-migration-wizard-page-connection-select', {
    template,

    props: {
        currentConnectionId: {
            type: String
        }
    },

    data() {
        return {
            selectedConnectionId: null,
            connections: []
        };
    },

    computed: {
        migrationConnectionStore() {
            return State.getStore('swag_migration_connection');
        }
    },

    created() {
        this.$emit('onChildRouteReadyChanged', false);
        this.migrationConnectionStore.getList({}).then((res) => {
            this.connections = res.items;
            this.onConnectionSelected();
        });
    },

    watch: {
        currentConnectionId: {
            immediate: true,
            handler(newConnectionId) {
                this.selectedConnectionId = newConnectionId;
                this.onConnectionSelected();
            }
        }
    },

    methods: {
        onConnectionSelected() {
            const connection = this.connections.find((con) => {
                return con.id === this.selectedConnectionId;
            });

            if (connection) {
                this.$emit('onChildRouteReadyChanged', true);
                this.$emit('onConnectionSelected', connection);
            } else {
                this.$emit('onChildRouteReadyChanged', false);
            }
        }
    }
});