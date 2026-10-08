// <x-modal name="…">. Config is read from data-* attributes so the markup
// needs no inline JavaScript.
export default () => ({
    show: false,
    name: '',

    init() {
        this.name = this.$el.dataset.modalName;
        this.show = this.$el.dataset.show === 'true';

        this.$watch('show', (visible) => {
            if (visible) {
                this.$nextTick(() => this.focusFirst());
            }
        });

        if (this.show) {
            this.$nextTick(() => this.focusFirst());
        }
    },

    openFor(name) {
        if (name === this.name) {
            this.show = true;
        }
    },

    closeFor(name) {
        if (name === this.name) {
            this.close();
        }
    },

    close() {
        this.show = false;
    },

    focusFirst() {
        const target = this.$el.querySelector('[autofocus], input:not([type=hidden]), select, textarea, button[type=submit]');
        target?.focus();
    },
});
