<template>
  <Modal :open="open" :title="title" :subtitle="subtitle" max-width="max-w-xl" @close="$emit('close')">
    <!-- Result slot: shown once a token resolves to a record -->
    <slot v-if="resolved" />

    <div v-else class="compact-modal-body">
      <div
        v-if="cameraError"
        role="alert"
        aria-live="assertive"
        class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-900 dark:border-red-500/50 dark:bg-red-950/40 dark:text-red-200"
      >
        <p class="font-semibold">Camera unavailable</p>
        <p class="mt-0.5">{{ cameraError }}</p>
        <button
          v-if="!cameraStarting && !cameraActive"
          type="button"
          class="mt-1.5 font-semibold underline underline-offset-2"
          @click="retryCamera"
        >
          Retry camera
        </button>
      </div>

      <div
        v-if="lookupError"
        role="alert"
        aria-live="assertive"
        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
      >
        {{ lookupError }}
      </div>

      <div class="overflow-hidden rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
        <div :id="viewportId" class="w-full" style="min-height: 200px;" />
        <div
          v-if="cameraStarting"
          class="border-t border-gray-200 px-3 py-2 text-center text-[11px] text-gray-500 dark:border-gray-700 dark:text-gray-400"
          aria-live="polite"
        >
          Requesting camera access…
        </div>
        <div
          v-else-if="cameraActive"
          class="border-t border-gray-200 px-3 py-2 text-center text-[11px] text-emerald-700 dark:border-gray-700 dark:text-emerald-300"
          aria-live="polite"
        >
          Camera ready. Hold the QR label in view.
        </div>
        <div
          v-else-if="!cameraError"
          class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 px-3 py-2 dark:border-gray-700"
        >
          <p class="text-[11px] text-gray-500 dark:text-gray-400">Camera preview is idle. Start it to scan a label.</p>
          <button
            type="button"
            class="compact-btn compact-btn--primary"
            @click="retryCamera"
          >
            Start camera
          </button>
        </div>
      </div>

      <div v-if="cameraDevices.length > 1" class="space-y-1">
        <label :for="`${viewportId}-select`" class="compact-label">Camera</label>
        <select
          :id="`${viewportId}-select`"
          v-model="selectedCameraId"
          class="compact-field"
          @change="restartCamera"
        >
          <option v-for="(camera, index) in cameraDevices" :key="camera.id" :value="camera.id">
            {{ camera.label || `Camera ${index + 1}` }}
          </option>
        </select>
      </div>

      <form class="space-y-1" @submit.prevent="submitManualToken">
        <label :for="`${viewportId}-manual`" class="compact-label">
          Enter QR token manually
        </label>
        <div class="flex gap-1.5">
          <input
            :id="`${viewportId}-manual`"
            v-model="manualToken"
            type="text"
            autocomplete="off"
            :placeholder="tokenPlaceholder"
            class="compact-field min-w-0 flex-1 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20"
            :disabled="loading"
          />
          <button
            type="submit"
            class="compact-btn compact-btn--primary shrink-0 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loading || !manualToken.trim()"
          >
            {{ loading ? 'Looking up…' : 'Look up' }}
          </button>
        </div>
      </form>
    </div>
  </Modal>
</template>

<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue';
import Modal from '../dialogs/Modal.vue';

/**
 * Shared QR capture surface: camera lifecycle, camera picker and manual token entry.
 *
 * The consumer owns the lookup, so this component only emits a normalised token and
 * swaps to its default slot once the caller reports a resolved record.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    viewportId: { type: String, default: 'qr-camera-viewport' },
    tokenPlaceholder: { type: String, default: 'dnhs_qr_…' },
    lookupError: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    resolved: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'lookup']);

// The square the user aims at is html5-qrcode's shaded scan region, whose size
// comes from `config.qrbox`. A hardcoded pixel value fights the dialog width:
// too large and the library drops the shading and the corner brackets
// altogether, too small and it leaves most of the feed unusable. Deriving it
// from the live feed keeps the square as large as the stream safely allows.
const QR_BOX_MIN_PX = 50; // library rejects anything smaller than this
const QR_BOX_FILL = 0.82; // leave a margin so the shaded border stays visible

function qrboxFor(viewfinderWidth, viewfinderHeight) {
    const width = Number(viewfinderWidth) || 0;
    const height = Number(viewfinderHeight) || 0;

    if (width < QR_BOX_MIN_PX || height < QR_BOX_MIN_PX) {
        return { width: QR_BOX_MIN_PX, height: QR_BOX_MIN_PX };
    }

    const side = Math.floor(Math.min(width, height) * QR_BOX_FILL);

    return { width: side, height: side };
}

const cameraError = ref('');
const cameraStarting = ref(false);
const cameraActive = ref(false);
const cameraDevices = ref([]);
const selectedCameraId = ref('');
const manualToken = ref('');

let scanner = null;
let startPromise = null;
let stopPromise = null;
let operationId = 0;

async function loadScannerLibrary() {
    const module = await (window.__html5QrcodeReady ?? import('html5-qrcode'));
    return module.Html5Qrcode;
}

function submitManualToken() {
    const token = manualToken.value.trim();
    if (!token || props.loading) return;
    emit('lookup', token);
}

async function startCamera() {
    if (!props.open || props.resolved) return;
    if (cameraStarting.value || cameraActive.value) return;
    if (stopPromise) await stopPromise;
    if (startPromise) return startPromise;
    if (!props.open || props.resolved) return;

    if (!window.isSecureContext) {
        cameraError.value = 'Camera access requires HTTPS or localhost. Use the token field below on this connection.';
        return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = 'This browser does not support camera access. Use the token field below instead.';
        return;
    }

    const viewport = document.getElementById(props.viewportId);
    if (!viewport) {
        cameraError.value = 'The camera view is not ready. Close and reopen the scanner.';
        return;
    }

    const currentOperation = operationId;
    const promise = startCameraAttempt(currentOperation, viewport);
    startPromise = promise;
    try {
        await promise;
    } finally {
        if (startPromise === promise) startPromise = null;
    }
}

async function startCameraAttempt(currentOperation, viewport) {
    cameraError.value = '';
    cameraStarting.value = true;
    let timeoutId;
    let activeScanner = null;

    try {
        const Html5Qrcode = await loadScannerLibrary();
        const cameraDiscovery = Html5Qrcode.getCameras();
        const discoveryResult = await Promise.race([
            cameraDiscovery.then((cameras) => ({ cameras })),
            new Promise((resolve) => {
                timeoutId = window.setTimeout(() => resolve({ timeout: true }), 12000);
            }),
        ]);
        if (timeoutId) window.clearTimeout(timeoutId);
        timeoutId = null;
        if (currentOperation !== operationId || !props.open) return;

        if (discoveryResult.timeout) {
            cameraError.value = 'Camera discovery is taking too long. Check the browser camera prompt, or enter the token below.';
            return;
        }

        const cameras = discoveryResult.cameras;
        if (!cameras.length) {
            throw new Error('No camera was found on this device.');
        }
        if (currentOperation !== operationId || !props.open) return;

        cameraDevices.value = cameras;
        if (!cameras.some((camera) => camera.id === selectedCameraId.value)) {
            const preferred = cameras.find((camera) => /back|rear|environment/i.test(camera.label)) ?? cameras[0];
            selectedCameraId.value = preferred.id;
        }

        activeScanner = new Html5Qrcode(viewport.id);
        scanner = activeScanner;
        const cameraStart = activeScanner.start(
            selectedCameraId.value,
            { fps: 10, qrbox: qrboxFor },
            (decodedText) => {
                const token = decodedText.trim();
                if (token) emit('lookup', token);
            },
            () => {},
        );
        timeoutId = window.setTimeout(() => {
            if (cameraStarting.value && scanner === activeScanner) {
                cameraError.value = 'Camera startup is taking longer than expected. Respond to any browser camera prompt; keep this window open while it finishes.';
            }
        }, 12000);
        await cameraStart;

        if (currentOperation !== operationId || !props.open || props.resolved) return;

        cameraActive.value = true;
        cameraError.value = '';
    } catch (cameraFailure) {
        if (currentOperation !== operationId || !props.open) return;
        cameraActive.value = false;
        if (activeScanner && scanner === activeScanner) {
            scanner = null;
            try { activeScanner.clear(); } catch {}
        }
        cameraError.value = describeCameraFailure(cameraFailure);
    } finally {
        if (timeoutId) window.clearTimeout(timeoutId);
        if (currentOperation === operationId) cameraStarting.value = false;
    }
}

function describeCameraFailure(failure) {
    const name = failure?.name ?? '';
    const reason = failure?.message ?? String(failure);
    const detail = reason.toLowerCase();

    if (name === 'NotAllowedError' || name === 'PermissionDeniedError' || detail.includes('permission')) {
        return 'Camera permission was denied or blocked. Allow camera access in your browser settings, then retry, or enter the QR token below.';
    }
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError' || detail.includes('no camera')) {
        return 'No camera was found. Connect or enable a camera, then retry, or enter the QR token below.';
    }
    if (name === 'NotReadableError' || name === 'TrackStartError') {
        return 'The camera could not be opened. It may be in use by another app; close that app and retry, or enter the QR token below.';
    }
    if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
        return 'The selected camera is unavailable. Choose another camera if listed, then retry, or enter the QR token below.';
    }
    if (name === 'SecurityError' || detail.includes('secure context')) {
        return 'Camera access requires HTTPS or localhost. Open the app over a secure connection, or enter the QR token below.';
    }
    if (name === 'AbortError') {
        return 'Camera startup was interrupted. Retry the camera, or enter the QR token below.';
    }

    return `Could not start the camera: ${reason}. Check the browser camera permission and try again, or enter the QR token below.`;
}

async function stopCamera() {
    if (stopPromise) return stopPromise;

    operationId += 1;
    cameraActive.value = false;
    cameraStarting.value = false;
    const promise = (async () => {
        if (startPromise) await startPromise;

        const activeScanner = scanner;
        scanner = null;
        if (!activeScanner) return;

        try { await activeScanner.stop(); } catch {}
        try { activeScanner.clear(); } catch {}
    })();
    stopPromise = promise;
    try {
        await promise;
    } finally {
        if (stopPromise === promise) stopPromise = null;
    }
}

function retryCamera() {
    cameraError.value = '';
    void startCamera();
}

async function restartCamera() {
    cameraError.value = '';
    await stopCamera();
    void startCamera();
}

function reset() {
    cameraError.value = '';
    cameraStarting.value = false;
    cameraActive.value = false;
    cameraDevices.value = [];
    selectedCameraId.value = '';
    manualToken.value = '';
}

watch(
    () => [props.open, props.resolved],
    async ([isOpen, isResolved]) => {
        if (!isOpen || isResolved) {
            await stopCamera();
            return;
        }

        reset();
        await nextTick();
        void startCamera();
    },
);

onUnmounted(() => {
    void stopCamera();
});
</script>