// <x-alert dismissible>
export default () => ({
    open: true,

    close() {
        this.open = false;
    },
});
