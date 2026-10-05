if (typeof HTMLMediaElement !== 'undefined' && !HTMLMediaElement.prototype.__dnhsPlayGuard) {
    const nativePlay = HTMLMediaElement.prototype.play;
    HTMLMediaElement.prototype.play = function (...args) {
        const result = nativePlay.apply(this, args);
        if (result && typeof result.catch === 'function') {
            result.catch((error) => {
                if (error && error.name === 'AbortError') {
                    return;
                }
                throw error;
            });
        }
        return result;
    };
    HTMLMediaElement.prototype.__dnhsPlayGuard = true;
}
