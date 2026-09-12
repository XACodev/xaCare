import { toPng } from 'html-to-image';
import jsQR from 'jsqr';

window.xacareCopyPrintImage = async function (elementId) {
    const node = document.getElementById(elementId);
    if (!node) return;

    const dataUrl = await toPng(node, { pixelRatio: 2 });
    const blob = await (await fetch(dataUrl)).blob();

    if (window.ClipboardItem && navigator.clipboard?.write) {
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
        return;
    }

    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = 'cotizacion.png';
    link.click();
};

window.xacareScanQr = function (videoElementId, canvasElementId, onDecoded) {
    const video = document.getElementById(videoElementId);
    const canvas = document.getElementById(canvasElementId);
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    let stream = null;
    let raf = null;
    let stopped = false;

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then((s) => {
        stream = s;
        video.srcObject = stream;
        video.setAttribute('playsinline', 'true');
        video.play();
        raf = requestAnimationFrame(tick);
    });

    function tick() {
        if (stopped) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            if (code) {
                onDecoded(code.data);
                return;
            }
        }
        raf = requestAnimationFrame(tick);
    }

    return {
        stop() {
            stopped = true;
            if (raf) cancelAnimationFrame(raf);
            if (stream) stream.getTracks().forEach((track) => track.stop());
        },
    };
};
