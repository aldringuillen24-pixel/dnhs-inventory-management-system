<template>
  <div v-if="canUseAssistant" @keydown.escape.window="open = false">
    <Transition name="ai-fade">
      <div v-if="open" class="fixed inset-0 z-[100000] bg-gray-900/40" @click="open = false" />
    </Transition>
    <Transition name="ai-drawer">
        <section
          v-if="open"
          class="fixed inset-y-0 right-0 z-[100001] flex w-full max-w-xl flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950"
          aria-label="AI assistant"
        >
          <header class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <div>
              <h2 class="text-sm font-semibold text-gray-800 dark:text-white/90">AI Assistant</h2>
              <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Inventory support</p>
            </div>
            <div class="flex items-center gap-1">
              <button
                v-if="messages.length > 0"
                type="button"
                class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300"
                :disabled="busy"
                @click="newChat"
                title="New chat"
                aria-label="New chat"
              >
                <LucideIcon :icon="MessageSquarePlus" class="h-4 w-4" />
              </button>
              <button
                v-if="conversations.length > 0"
                type="button"
                class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300"
                :disabled="busy"
                @click="showHistory = !showHistory"
                title="Chat history"
                aria-label="Chat history"
              >
                <LucideIcon :icon="History" class="h-4 w-4" />
              </button>
              <button
                v-if="busy && requestController"
                type="button"
                class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300"
                @click="cancelRequest"
                title="Cancel request"
                aria-label="Cancel request"
              >
                <LucideIcon :icon="X" class="h-4 w-4" />
              </button>
              <button type="button" class="rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" @click="reset">Reset</button>
              <button type="button" class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300" @click="open = false" title="Close assistant" aria-label="Close assistant">
                <LucideIcon :icon="X" class="h-5 w-5" />
              </button>
            </div>
          </header>

          <!-- Chat History -->
          <div v-if="showHistory" class="flex-1 overscroll-contain overflow-y-auto px-4 py-4">
            <div class="mb-4 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Chat history</h3>
              <span class="text-xs text-gray-400">{{ conversations.length }} saved</span>
            </div>
            <div v-if="conversations.length === 0" class="text-xs text-gray-500 dark:text-gray-400">No saved conversations.</div>
            <div v-else class="space-y-2">
              <div v-for="conversation in conversations" :key="conversation.id" class="group flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/50 dark:border-gray-800 dark:hover:border-brand-500/40 dark:hover:bg-white/5">
                <button type="button" @click="loadConversation(conversation)" class="min-w-0 flex-1 text-left">
                  <span class="block truncate text-sm font-medium text-gray-700 dark:text-gray-200">{{ conversation.title }}</span>
                  <span class="mt-1 block text-[10px] text-gray-400">{{ conversation.updatedAt }}</span>
                </button>
                <button type="button" @click="deleteConversation(conversation.id)" class="rounded p-1 text-gray-400 hover:text-red-500" title="Delete conversation" aria-label="Delete conversation">
                  <LucideIcon :icon="Trash2" class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Chat Messages -->
          <div v-else ref="logRef" class="flex-1 space-y-3 overscroll-contain overflow-y-auto p-4">
            <div v-if="!messages.length" class="rounded-md bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-400">
              {{ welcomeMessage }}
            </div>

            <div v-for="(message, index) in messages" :key="index" class="ai-message flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start gap-2'">
              <div v-if="message.role !== 'user'" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
              </div>
              <div class="max-w-[85%] rounded-2xl px-3 py-2 text-sm" :class="message.role === 'user' ? 'rounded-br-sm bg-brand-500 text-white' : 'rounded-bl-sm bg-gray-100 text-gray-800 dark:bg-white/5 dark:text-gray-200'">
              <div v-if="message.role !== 'user'" class="mb-1 flex items-center gap-1 text-[10px] opacity-70">
                <span v-if="message.time">{{ message.time }}</span>
                <span class="ml-auto flex items-center gap-0.5">
                <button
                  v-if="message.text"
                  type="button"
                  class="flex items-center gap-1 rounded px-1.5 py-0.5 font-medium transition hover:bg-black/5 dark:hover:bg-white/10"
                  :aria-label="copiedIndex === index ? 'Copied' : 'Copy reply'"
                  :title="copiedIndex === index ? 'Copied' : 'Copy reply'"
                  @click="copyMessage(message.text, index)"
                >
                  <svg v-if="copiedIndex === index" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                  <svg v-else width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                  {{ copiedIndex === index ? 'Copied' : 'Copy' }}
                </button>
                <button
                  v-if="index === messages.length - 1"
                  type="button"
                  class="flex items-center gap-1 rounded px-1.5 py-0.5 font-medium transition hover:bg-black/5 disabled:opacity-40 dark:hover:bg-white/10"
                  :disabled="busy"
                  aria-label="Retry reply"
                  title="Retry reply"
                  @click="regenerate(index)"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                  Retry
                </button>
                <button
                  type="button"
                  class="rounded p-1 transition hover:bg-black/5 dark:hover:bg-white/10"
                  :class="message.feedback === 'up' ? 'text-brand-600 dark:text-brand-400' : ''"
                  aria-label="Good reply"
                  title="Good reply"
                  @click="setFeedback(message, 'up')"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" :fill="message.feedback === 'up' ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
                </button>
                <button
                  type="button"
                  class="rounded p-1 transition hover:bg-black/5 dark:hover:bg-white/10"
                  :class="message.feedback === 'down' ? 'text-red-500' : ''"
                  aria-label="Bad reply"
                  title="Bad reply"
                  @click="setFeedback(message, 'down')"
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" :fill="message.feedback === 'down' ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/></svg>
                </button>
                </span>
              </div>
              <div v-if="message.text" :class="isLongReply(message) && !message.expanded ? 'ai-clamp relative' : 'relative'">
                <p class="whitespace-pre-line" v-html="renderMarkdown(message.text)"></p>
                <div v-if="isLongReply(message) && !message.expanded" class="pointer-events-none absolute inset-x-0 bottom-0 h-10 bg-gradient-to-t to-transparent" :class="message.role === 'user' ? 'from-brand-500' : 'from-gray-100 dark:from-white/5'"></div>
              </div>
              <button v-if="isLongReply(message)" type="button" class="mt-1 text-[11px] font-semibold opacity-70 transition hover:opacity-100" @click="message.expanded = !message.expanded">
                {{ message.expanded ? 'Show less' : 'Show more' }}
              </button>
              <p v-if="message.role === 'user' && message.time" class="mt-1 text-right text-[10px] opacity-60">{{ message.time }}</p>

              <form v-if="message.comparisonForm && !message.comparisonForm.completed" class="mt-3 space-y-3 border-t border-gray-200 pt-3 dark:border-white/10" @submit.prevent="submitComparison(message)">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <label class="block space-y-1 text-xs font-semibold">
                    Metric
                    <select v-model="message.comparisonForm.metric" required class="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm font-normal dark:border-gray-700 dark:bg-gray-800" @change="onComparisonMetricChange(message.comparisonForm)">
                      <option value="">Choose a metric</option>
                      <option v-for="metric in message.comparisonForm.metrics ?? []" :key="metric.value" :value="metric.value">{{ metric.label }}</option>
                    </select>
                  </label>
                  <label class="block space-y-1 text-xs font-semibold">
                    Inventory scope
                    <select v-model="message.comparisonForm.scope" required class="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm font-normal dark:border-gray-700 dark:bg-gray-800" @change="onComparisonScopeChange(message.comparisonForm)">
                      <option value="">Choose a scope</option>
                      <option value="all">All inventory</option>
                      <option value="item">One item</option>
                    </select>
                  </label>
                </div>
                <label v-if="message.comparisonForm.scope === 'item' && (message.comparisonForm.item_options ?? []).length" class="block space-y-1 text-xs font-semibold">
                  Matching inventory records
                  <select v-model="message.comparisonForm.item_id" required class="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm font-normal dark:border-gray-700 dark:bg-gray-800">
                    <option value="">Choose the exact record</option>
                    <option v-for="item in message.comparisonForm.item_options" :key="item.value" :value="item.value">{{ item.label }}</option>
                  </select>
                </label>
                <label v-if="message.comparisonForm.scope === 'item' && !(message.comparisonForm.item_options ?? []).length" class="block space-y-1 text-xs font-semibold">
                  Exact item name
                  <input v-model="message.comparisonForm.item_name" required maxlength="255" class="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm font-normal dark:border-gray-700 dark:bg-gray-800" @input="message.comparisonForm.item_id = null" />
                </label>
                <label v-if="message.comparisonForm.scope === 'all' && comparisonMetricNeedsUnit(message.comparisonForm)" class="block space-y-1 text-xs font-semibold">
                  Inventory unit
                  <input v-model="message.comparisonForm.unit" :list="`ai-comparison-units-${index}`" required maxlength="64" autocomplete="off" class="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm font-normal dark:border-gray-700 dark:bg-gray-800" />
                  <datalist :id="`ai-comparison-units-${index}`">
                    <option v-for="unit in message.comparisonForm.units ?? []" :key="unit" :value="unit"></option>
                  </datalist>
                </label>
                <p v-if="message.comparisonForm.scope === 'item' && comparisonMetricNeedsUnit(message.comparisonForm) && message.comparisonForm.item_unit" class="border-l-2 border-brand-500 pl-2 text-xs font-normal opacity-80">Unit: {{ message.comparisonForm.item_unit }}</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <label class="block space-y-1 text-xs font-semibold">
                    First month
                    <input v-model="message.comparisonForm.month_one" type="month" :max="maxMonth" required class="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm font-normal dark:border-gray-700 dark:bg-gray-800" />
                  </label>
                  <label class="block space-y-1 text-xs font-semibold">
                    Second month
                    <input v-model="message.comparisonForm.month_two" type="month" :max="maxMonth" required class="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm font-normal dark:border-gray-700 dark:bg-gray-800" />
                  </label>
                </div>
                <p v-if="message.comparisonForm.error" role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-500/10 dark:text-red-300">{{ message.comparisonForm.error }}</p>
                <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-200 pt-3 dark:border-white/10">
                  <button type="button" :disabled="busy" class="rounded-md px-3 py-2 text-xs font-medium opacity-70 hover:opacity-100 disabled:opacity-50" @click="cancelComparison(message)">Cancel</button>
                  <button type="submit" :disabled="busy" class="rounded-md bg-brand-500 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-600 disabled:opacity-50">{{ busy ? 'Calculating…' : 'Confirm and calculate' }}</button>
                </div>
              </form>

              <div v-if="message.comparisonResult" role="status" class="mt-3 w-full border-t border-gray-200 pt-3 dark:border-white/10">
                <p class="text-xs font-semibold">{{ message.comparisonResult.metric_label }}: {{ message.comparisonResult.scope_label }}</p>
                <div :id="`ai-comparison-chart-${index}`" class="mt-2 min-h-[240px]"></div>
                <table class="mt-2 w-full border-collapse text-left text-xs">
                  <caption class="sr-only">Comparison totals and changes for each unit</caption>
                  <thead>
                    <tr>
                      <th scope="col" class="p-1">Unit</th>
                      <th scope="col" class="p-1">{{ message.comparisonResult.months[0].label }}</th>
                      <th scope="col" class="p-1">{{ message.comparisonResult.months[1].label }}</th>
                      <th scope="col" class="p-1">Difference</th>
                      <th scope="col" class="p-1">Change</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in message.comparisonResult.series" :key="row.unit" class="border-t border-gray-200 dark:border-white/10">
                      <th scope="row" class="p-1 font-medium">{{ row.unit }}</th>
                      <td class="p-1">{{ comparisonStatus(row.period_one_status, row.period_one) }}</td>
                      <td class="p-1">{{ comparisonStatus(row.period_two_status, row.period_two) }}</td>
                      <td class="p-1">{{ row.difference === null ? 'Unavailable' : row.difference }}</td>
                      <td class="p-1">{{ comparisonPercentageStatus(row) }}</td>
                    </tr>
                  </tbody>
                </table>
                <div class="mt-3 border-l-2 border-brand-500 pl-3">
                  <p class="text-[10px] font-semibold uppercase opacity-70">AI insight</p>
                  <p class="mt-1 whitespace-pre-line text-sm leading-relaxed">{{ message.comparisonResult.explanation }}</p>
                </div>
              </div>
              </div>
            </div>
            <p v-if="busy" class="animate-pulse text-xs text-gray-500 dark:text-gray-400">Thinking…</p>
            <p v-if="notice" class="rounded-md border border-warning-200 bg-warning-50 p-3 text-xs text-warning-700 dark:border-warning-800 dark:bg-warning-500/10 dark:text-warning-400">{{ notice }}</p>
          </div>

          <form class="border-t border-gray-200 p-3 dark:border-gray-800" @submit.prevent="send">
            <div v-if="questionSuggestions.length" class="mb-3">
              <div class="mb-2 flex items-center justify-between">
                <p class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Try asking</p>
                <button
                  type="button"
                  :aria-expanded="suggestionsExpanded"
                  :aria-label="suggestionsExpanded ? 'Hide suggested questions' : 'Show suggested questions'"
                  :title="suggestionsExpanded ? 'Hide suggested questions' : 'Show suggested questions'"
                  class="flex h-6 w-6 items-center justify-center rounded text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/5 dark:hover:text-gray-200"
                  @click="suggestionsExpanded = !suggestionsExpanded"
                >
                  <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': suggestionsExpanded }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
              </div>
              <Transition name="ai-suggest">
                <div v-show="suggestionsExpanded" class="flex flex-wrap gap-2" role="group" aria-label="Suggested questions">
                <button
                  v-for="(suggestion, index) in questionSuggestions"
                  :key="index"
                  type="button"
                  :disabled="busy"
                  class="shrink-0 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-600 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                  @click="askSuggestion(suggestion)"
                >
                  {{ suggestion }}
                </button>
              </div>
              </Transition>
            </div>
            <div class="flex gap-2">
            <input
              v-model="draft"
              type="text"
              maxlength="1000"
              placeholder="Ask an inventory question…"
              aria-label="Ask an inventory question"
              class="min-w-0 flex-1 rounded-md border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            />
            <button
              type="submit"
              class="rounded-md bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
              :disabled="busy || !draft.trim()"
            >
              {{ busy ? '…' : 'Send' }}
            </button>
            </div>
          </form>
        </section>
      </Transition>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { MessageSquarePlus, History, X, Trash2 } from 'lucide';
import api from '../../lib/axios';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import LucideIcon from '../ui/data-display/LucideIcon.vue';

// UX-only mirror of the server policy (App\Services\AiCapabilityPolicy is
// authoritative and still enforces every call). The launcher hides for roles
// the server would reject, so nobody is promised an assistant they can't use.
const ASSISTANT_ROLES = ['Property Custodian'];

const authStore = useAuthStore();
const uiStore = useUiStore();

// Drawer visibility lives in the shared UI store so the header icon can open it.
const open = computed({
  get: () => uiStore.aiOpen,
  set: (value) => uiStore.setAiOpen(value),
});
const canUseAssistant = computed(() => ASSISTANT_ROLES.includes(authStore.role));

const draft = ref('');
const busy = ref(false);
const notice = ref('');
const messages = ref([]);
const logRef = ref(null);
const showHistory = ref(false);
const suggestionsExpanded = ref(false);
const conversations = ref([]);
const welcomeMessage = ref('Ask about stock, assignments, maintenance, or the demand forecast. The server enforces what your role may see.');
const questionSuggestions = ref([]);
const requestController = ref(null);
const comparisonCharts = ref({});
const copiedIndex = ref(null);
let copiedTimer = null;

function nowTime() {
  return new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

async function copyMessage(text, index) {
  let done = false;
  try {
    await navigator.clipboard.writeText(text ?? '');
    done = true;
  } catch {
    const area = document.createElement('textarea');
    area.value = text ?? '';
    document.body.appendChild(area);
    area.select();
    try {
      done = document.execCommand('copy');
    } catch {
      done = false;
    }
    area.remove();
  }
  if (!done) return;
  copiedIndex.value = index;
  clearTimeout(copiedTimer);
  copiedTimer = setTimeout(() => {
    copiedIndex.value = null;
  }, 1500);
}

const maxMonth = computed(() => {
  const date = new Date();
  date.setMonth(date.getMonth() - 1);
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
});

function historyKey() {
  return `dnhs-ai-conversations.${authStore.user?.id ?? 'guest'}`;
}

function activeConversationKey() {
  return `dnhs-ai-active-conversation.${authStore.user?.id ?? 'guest'}`;
}

// Lock background scroll while the drawer is open so wheel/touch
// gestures over the panel never move the page behind it.
watch(open, (visible) => {
  document.body.style.overflow = visible ? 'hidden' : '';

  // The message log is behind `v-if="open"`, so closing the drawer destroys it and
  // reopening mounts a fresh element at scrollTop 0 — which left a restored
  // conversation showing its oldest message instead of the newest. Jump back to
  // the bottom whenever the drawer becomes visible again.
  if (visible && !showHistory.value) {
    scrollDown();
  }
}, { immediate: true });

// Same problem when returning from the history list: the log is re-created by
// the v-else branch, so it also needs re-anchoring to the latest message.
watch(showHistory, (historyVisible) => {
  if (open.value && !historyVisible) {
    scrollDown();
  }
});

onUnmounted(() => {
  document.body.style.overflow = '';
});

// Load persisted state on mount
onMounted(() => {
  loadConversations();
  loadActiveConversation();
  loadWelcomeMessage();
  loadQuestionSuggestions();
});

// Watch for user changes to reload per-user state
watch(
  () => authStore.user?.id,
  () => {
    loadConversations();
    if (open.value) {
      loadActiveConversation();
      loadWelcomeMessage();
      loadQuestionSuggestions();
    }
  }
);

function loadConversations() {
  try {
    conversations.value = JSON.parse(localStorage.getItem(historyKey()) || '[]');
  } catch {
    conversations.value = [];
  }
}

function loadActiveConversation() {
  try {
    const activeMessages = JSON.parse(localStorage.getItem(activeConversationKey()) || 'null');
    if (Array.isArray(activeMessages) && activeMessages.length) {
      messages.value = activeMessages;
    } else {
      messages.value = [];
    }
  } catch {
    messages.value = [];
  }
}

function persistActiveConversation() {
  localStorage.setItem(activeConversationKey(), JSON.stringify(messages.value));
}

function loadWelcomeMessage() {
  const role = authStore.role;
  if (!role) {
    welcomeMessage.value = 'Ask about stock, assignments, maintenance, or the demand forecast. The server enforces what your role may see.';
    return;
  }

  const roleHelpText = {
    'Property Custodian': 'I can help with inventory availability, assigned items, requests, inventory locations, low-stock items, forecasts, assignment records, maintenance, disposal, purchase history, item status, reports, system summaries, and inventory valuation. For what to buy first, open Demand Forecast and use AI Decision Support.',
    'School Head': 'I can help with approved inventory summaries and reports.',
    'End User': 'I can help you check your assigned items, your requests, pending requests, and available inventory.',
    'Administrator': 'I can help with inventory availability, assigned items, requests, inventory locations, low-stock items, forecasts, assignment records, maintenance, disposal, purchase history, item status, reports, system summaries, and inventory valuation. For what to buy first, open Demand Forecast and use AI Decision Support.',
  };

  welcomeMessage.value = roleHelpText[role] || 'Ask about stock, assignments, maintenance, or the demand forecast. The server enforces what your role may see.';
}

function loadQuestionSuggestions() {
  const role = authStore.role;
  const allSuggestions = [
    { roles: ['Property Custodian', 'Administrator'], text: 'How many items are available?' },
    { roles: ['Property Custodian', 'Administrator'], text: 'Show inventory stock' },
    { roles: ['Property Custodian', 'Administrator'], text: 'How many pending requests?' },
    { roles: ['Property Custodian', 'Administrator'], text: 'Which items are low in stock?' },
    { roles: ['Property Custodian', 'Administrator'], text: 'Explain the demand forecast' },
    { roles: ['Property Custodian', 'Administrator'], text: 'What should we purchase first?' },
    { roles: ['Property Custodian', 'Administrator'], text: 'Where is an item stored?' },
    { roles: ['Property Custodian', 'Administrator'], text: 'Show items needing maintenance' },
    { roles: ['End User'], text: 'What items are assigned to me?' },
    { roles: ['End User'], text: 'What is the status of my requests?' },
    { roles: ['School Head'], text: 'Show executive reports' },
    { roles: ['School Head'], text: 'Show system summary' },
  ];

  if (!role) {
    questionSuggestions.value = [];
    return;
  }

  questionSuggestions.value = allSuggestions
    .filter((s) => s.roles.includes(role))
    .slice(0, 8)
    .map((s) => s.text);
}

function renderMarkdown(text) {
  if (!text) return '';
  try {
    const raw = marked.parse(text, { breaks: true, gfm: true });
    return DOMPurify.sanitize(raw);
  } catch {
    return text;
  }
}

async function scrollDown() {
  await nextTick();
  if (logRef.value) logRef.value.scrollTop = logRef.value.scrollHeight;
}

function askSuggestion(question) {
  draft.value = question;
  send();
}

function saveConversation() {
  const userMessages = messages.value.filter((message) => message.role === 'user');
  if (!userMessages.length) return;

  const conversation = {
    id: Date.now(),
    title: userMessages[0].text.slice(0, 42),
    messages: messages.value,
    updatedAt: new Date().toLocaleString(),
  };

  conversations.value = [conversation, ...conversations.value.filter((item) => item.id !== conversation.id)].slice(0, 20);
  localStorage.setItem(historyKey(), JSON.stringify(conversations.value));
  persistActiveConversation();
}

async function newChat() {
  if (busy.value) return;
  busy.value = true;
  try {
    await api.post('/ai/chat/reset');
    saveConversation();
    destroyComparisonCharts();
    messages.value = [
      { role: 'ai', text: welcomeMessage.value, time: 'Now' }
    ];
    localStorage.removeItem(activeConversationKey());
    draft.value = '';
    showHistory.value = false;
  } catch (error) {
    messages.value.push({ role: 'ai', text: 'Unable to start a new chat. Please try again.', time: 'Now' });
  } finally {
    busy.value = false;
    await scrollDown();
  }
}

async function loadConversation(conversation) {
  if (busy.value) return;
  busy.value = true;
  try {
    await api.post('/ai/chat/reset');
    destroyComparisonCharts();
    messages.value = conversation.messages;
    showHistory.value = false;
    await nextTick(() => {
      messages.value.forEach((message, index) => {
        if (message.comparisonResult) renderComparisonChart(index, message.comparisonResult);
      });
    });
    await scrollDown();
  } catch (error) {
    messages.value.push({ role: 'ai', text: 'Unable to open this chat. Please try again.', time: 'Now' });
  } finally {
    busy.value = false;
    await scrollDown();
  }
}

function deleteConversation(id) {
  conversations.value = conversations.value.filter((conversation) => conversation.id !== id);
  localStorage.setItem(historyKey(), JSON.stringify(conversations.value));
}

function comparisonStatus(status, value) {
  if (status === 'unavailable') return 'history unavailable';
  if (status === 'verified_zero') return `${value} (verified zero activity)`;
  return `${value} (activity recorded)`;
}

function comparisonSummaryText(result) {
  const head = `${result?.metric_label ?? 'Comparison'} (${result?.scope_label ?? ''})`;
  const rows = Array.isArray(result?.series) ? result.series.slice(0, 3) : [];
  if (!rows.length) {
    return head;
  }
  const lines = rows.map((row) => `${row.unit ?? '—'}: ${row.period_one ?? '—'} → ${row.period_two ?? '—'}`);
  return `${head}\n${lines.join('\n')}`;
}

function comparisonPercentageStatus(row) {
  if (row.percentage_change_status === 'zero_denominator') return 'Unavailable (first period is zero)';
  if (row.percentage_change === null) return 'Unavailable';
  return `${row.percentage_change}%`;
}

function comparisonMetricNeedsUnit(form) {
  const quantityMetrics = form.quantity_metrics ?? [
    'stock_in_quantity',
    'stock_out_quantity',
    'request_quantity',
    'assignment_quantity',
    'transfer_quantity',
    'disposal_quantity',
  ];
  return quantityMetrics.includes(form.metric);
}

function onComparisonMetricChange(form) {
  if (!comparisonMetricNeedsUnit(form)) {
    form.unit = null;
  }
}

function onComparisonScopeChange(form) {
  form.unit = null;
  form.item_id = null;
  form.item_name = null;
}

function setFeedback(message, value) {
  message.feedback = message.feedback === value ? null : value;
  persistActiveConversation();
}

function isLongReply(message) {
  return (message.text?.length ?? 0) > 450 && !message.comparisonForm && !message.comparisonResult;
}

function regenerate(index) {
  if (busy.value) return;
  let userIdx = -1;
  for (let i = index; i >= 0; i--) {
    if (messages.value[i].role === 'user') {
      userIdx = i;
      break;
    }
  }
  if (userIdx === -1) return;
  const text = messages.value[userIdx].text;
  messages.value = messages.value.slice(0, userIdx);
  persistActiveConversation();
  draft.value = text;
  send();
}

function cancelComparison(message) {
  message.comparisonForm = null;
  persistActiveConversation();
}

function destroyComparisonCharts() {
  Object.values(comparisonCharts.value).forEach((chart) => {
    try {
      chart?.destroy();
    } catch {
      // Element already removed: nothing to clean up.
    }
  });
  comparisonCharts.value = {};
}

let apexChartsPromise = null;

function loadApexCharts() {
  apexChartsPromise ??= import('apexcharts').then((module) => module.default ?? module);
  return apexChartsPromise;
}

function renderComparisonChart(index, result) {
  loadApexCharts().then((ApexCharts) => nextTick(() => {
    const element = document.getElementById(`ai-comparison-chart-${index}`);
    const chartData = result?.chart;
    if (!element || !chartData || !Array.isArray(chartData.categories) || !Array.isArray(chartData.series)) return;
    if (comparisonCharts.value[index]) {
      try {
        comparisonCharts.value[index].destroy();
      } catch {
        // Stale chart instance: render fresh below.
      }
    }
    const chart = new ApexCharts(element, {
      chart: { type: 'bar', height: 240, toolbar: { show: false } },
      plotOptions: { bar: { columnWidth: '48%', borderRadius: 2 } },
      dataLabels: { enabled: false },
      series: chartData.series,
      xaxis: { categories: chartData.categories },
      yaxis: { title: { text: `${result.metric_label ?? ''} (${chartData.unit ?? ''})` } },
      tooltip: {
        y: {
          formatter: (value, { dataPointIndex }) => {
            const status = chartData.statuses?.[dataPointIndex];
            if (status === 'history unavailable') return 'history unavailable';
            return `${value ?? '—'} (${status ?? 'activity recorded'})`;
          },
        },
      },
      legend: { position: 'bottom' },
      colors: ['#465fff'],
      noData: { text: 'No verified activity to chart' },
    });
    chart.render();
    comparisonCharts.value[index] = chart;
  }));
}

async function submitComparison(message) {
  if (busy.value) return;
  message.comparisonForm.error = '';
  busy.value = true;
  try {
    const { data: payload } = await api.post('/ai/comparison', {
      confirmed: true,
      metric: message.comparisonForm.metric,
      scope: message.comparisonForm.scope,
      item_id: message.comparisonForm.item_id,
      item_name: message.comparisonForm.item_name,
      unit: message.comparisonForm.unit,
      month_one: message.comparisonForm.month_one,
      month_two: message.comparisonForm.month_two,
    });
    message.comparisonForm.completed = true;
    if (!payload.result || !Array.isArray(payload.result.months) || !Array.isArray(payload.result.series)) {
      message.comparisonForm.completed = false;
      message.comparisonForm.error = payload.message || 'The comparison returned no usable data. Please try again.';
      return;
    }
    messages.value.push({
      role: 'assistant',
      text: `Here are the calculated results.\n${comparisonSummaryText(payload.result)}`,
      time: nowTime(),
      comparisonResult: payload.result,
    });
    persistActiveConversation();
    saveConversation();
    renderComparisonChart(messages.value.length - 1, payload.result);
    await scrollDown();
  } catch (error) {
    const payload = error?.response?.data;
    const fieldError = Object.values(payload?.errors || {}).flat()[0];
    message.comparisonForm.error = fieldError
      || (typeof payload?.message === 'string' ? payload.message : null)
      || 'Connection error. Please try again.';
  } finally {
    busy.value = false;
  }
}

function cancelRequest() {
  if (!busy.value || !requestController.value) return;
  requestController.value.abort();
  requestController.value = null;
  busy.value = false;
  scrollDown();
}

function isCancelError(requestError) {
  return requestError?.code === 'ERR_CANCELED'
    || requestError?.name === 'CanceledError'
    || requestError?.name === 'AbortError';
}

async function send() {
  const text = draft.value.trim();
  if (!text || busy.value) return;

  busy.value = true;
  notice.value = '';
  messages.value.push({ role: 'user', text, time: nowTime() });
  draft.value = '';
  await scrollDown();

  const controller = new AbortController();
  requestController.value = controller;

  try {
    const { data } = await api.post(
      '/ai/chat',
      { message: text },
      { signal: controller.signal }
    );

    if (data.comparison_form) {
      messages.value.push({
        role: 'assistant',
        text: data.reply ?? 'Review the comparison details and confirm to calculate.',
        time: nowTime(),
        comparisonForm: { ...data.comparison_form, completed: false, error: '' },
      });
    } else {
      messages.value.push({ role: 'assistant', text: data.reply ?? 'No reply was returned.', time: nowTime() });
    }
    persistActiveConversation();
    saveConversation();
  } catch (requestError) {
    if (isCancelError(requestError)) return;

    const status = requestError?.response?.status;
    if (status === 403) {
      notice.value = requestError?.response?.data?.message ?? 'The AI assistant is not available for your role.';
    } else if (status === 422) {
      notice.value = requestError?.response?.data?.message ?? 'Please enter a question of up to 1000 characters.';
    } else {
      notice.value = requestError?.response?.data?.message ?? 'Unable to process your question at this moment. Please try again.';
    }
  } finally {
    requestController.value = null;
    busy.value = false;
    await scrollDown();
  }
}

async function reset() {
  try {
    await api.post('/ai/chat/reset');
  } finally {
    destroyComparisonCharts();
    messages.value = [];
    notice.value = '';
    localStorage.removeItem(activeConversationKey());
  }
}
</script>

<style scoped>
.ai-drawer-enter-active,
.ai-drawer-leave-active {
  transition: transform 0.3s ease;
}
.ai-drawer-enter-from,
.ai-drawer-leave-to {
  transform: translateX(100%);
}

.ai-fade-enter-active,
.ai-fade-leave-active {
  transition: opacity 0.25s ease;
}
.ai-fade-enter-from,
.ai-fade-leave-to {
  opacity: 0;
}

.ai-suggest-enter-active,
.ai-suggest-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
  overflow: hidden;
}
.ai-suggest-enter-from,
.ai-suggest-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
.ai-suggest-enter-to,
.ai-suggest-leave-from {
  opacity: 1;
  transform: translateY(0);
}

.ai-message {
  animation: ai-msg-in 0.25s ease both;
}
@keyframes ai-msg-in {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.ai-clamp > p {
  display: -webkit-box;
  -webkit-line-clamp: 6;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
