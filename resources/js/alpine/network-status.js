// Online / offline badge (controller app).
export default () => ({
    online: navigator.onLine,

    init() {
        window.addEventListener('online', () => (this.online = true));
        window.addEventListener('offline', () => (this.online = false));
    },
});
