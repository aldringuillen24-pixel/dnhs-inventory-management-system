import { ref } from 'vue';

// Shared in-memory server-data cache: one rhythm for every page.
//
// - remember: first visit stores the response under page + params
// - instant back: remembered copies render immediately, then verify quietly
// - forget: mutations wipe everything; unmount wipes the page
// - silent verify: background failures never surface; late responses that
//   arrived after the user moved on are discarded
// The server stays the source of truth. Nothing here persists to storage.
const entries = ref(new Map());

function buildKey(page, params) {
  return `${page}:${JSON.stringify(params ?? {})}`;
}

export function readPageCache(page, params) {
  return entries.value.get(buildKey(page, params));
}

export function writePageCache(page, params, data) {
  entries.value.set(buildKey(page, params), data);
}

export function forgetPageCache(page) {
  if (!page) {
    entries.value.clear();
    return;
  }

  for (const key of [...entries.value.keys()]) {
    if (key === page || key.startsWith(`${page}:`)) {
      entries.value.delete(key);
    }
  }
}

export async function loadCachedPage({
  page,
  params,
  background = false,
  fetchData,
  applyData,
  isCurrent,
  onStart,
  onDone,
  onError,
}) {
  const key = buildKey(page, params);
  const cached = entries.value.get(key);

  if (cached && !background) {
    applyData(cached);
    onDone(true);
    loadCachedPage({
      page,
      params,
      background: true,
      fetchData,
      applyData,
      isCurrent,
      onStart,
      onDone,
      onError,
    });
    return;
  }

  if (!background) {
    onStart();
  }

  try {
    const data = await fetchData();
    entries.value.set(key, data);
    if (!isCurrent || isCurrent()) {
      applyData(data);
    }
  } catch (requestError) {
    if (!background) {
      onError(requestError);
    }
  } finally {
    if (!background) {
      onDone(false);
    }
  }
}
