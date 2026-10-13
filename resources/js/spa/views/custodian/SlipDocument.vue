<template>
  <div class="grid min-w-0 gap-5 lg:grid-cols-2">
    <section class="min-w-0 space-y-4 rounded-md border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-800/40">
      <h3 class="text-sm font-bold text-gray-900 dark:text-white">Release details</h3>

      <div class="grid gap-3 sm:grid-cols-2">
        <label class="slip-field sm:col-span-2">
          <span>Recipient name</span>
          <input v-model="fields.recipient_name" type="text" maxlength="150" readonly />
        </label>
        <label class="slip-field">
          <span>Department / Unit</span>
          <input v-model="fields.department_unit" type="text" maxlength="150" />
        </label>
        <label class="slip-field">
          <span>Designation</span>
          <input v-model="fields.designation" type="text" maxlength="150" />
        </label>
        <label class="slip-field">
          <span>Expected return date</span>
          <input v-model="fields.expected_return_date" type="date" />
        </label>
        <label class="slip-field">
          <span>Actual return date</span>
          <input v-model="fields.actual_return_date" type="date" />
        </label>
      </div>

      <fieldset>
        <legend class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-300">Purpose of release</legend>
        <div class="grid gap-2 sm:grid-cols-2">
          <label v-for="purpose in purposes" :key="purpose" class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
            <input
              type="radio"
              name="release-purpose"
              :checked="fields.purpose === purpose"
              @change="fields.purpose = purpose"
            />
            {{ purpose }}
          </label>
        </div>
      </fieldset>

      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold text-gray-700 dark:text-gray-300">Condition upon release</legend>
        <label v-for="line in lines" :key="line.item_id" class="slip-field">
          <span>{{ line.item_no }} — {{ line.description }}</span>
          <input
            v-model="fields.conditions[line.item_id]"
            type="text"
            maxlength="100"
            placeholder="Enter condition"
          />
        </label>
      </fieldset>

      <fieldset class="space-y-3">
        <legend class="text-xs font-semibold text-gray-700 dark:text-gray-300">Signatories</legend>
        <div v-for="signatory in signatories" :key="signatory.key" class="grid gap-2 rounded-md border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2">
          <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 sm:col-span-2">{{ signatory.label }}</p>
          <label class="slip-field">
            <span>Printed name</span>
            <input
              v-model="fields[`${signatory.key}_name`]"
              type="text"
              maxlength="150"
              :readonly="['released_by', 'received_by'].includes(signatory.key)"
            />
          </label>
          <label class="slip-field sm:col-span-2">
            <span>Date</span>
            <input v-model="fields[`${signatory.key}_date`]" type="date" />
          </label>
        </div>
      </fieldset>
    </section>

    <section class="slip-preview">
      <h3 class="mb-2 text-sm font-bold text-gray-900 dark:text-white">PDF preview</h3>
      <div class="preview-scroll">
        <article class="slip-doc">
          <p class="slip-title"><strong>PROPERTY RELEASE SLIP</strong></p>

          <p><strong>Document Control No.</strong> {{ docNo }}</p>
          <p><strong>Date:</strong> {{ documentDate }}</p>

          <p class="slip-section">RECIPIENT INFORMATION</p>
          <p>Name of Requestor / Recipient: <u>{{ fields.recipient_name || '____________________________' }}</u></p>
          <p>Department / Unit: <u>{{ fields.department_unit || '_______________________' }}</u></p>
          <p>Designation: <u>{{ fields.designation || '___________________________' }}</u></p>
          <p>
            Purpose of Release:
            <span v-for="purpose in purposes" :key="purpose" class="preview-purpose">
              [{{ fields.purpose === purpose ? 'X' : ' ' }}] {{ purpose }}
            </span>
          </p>

          <p class="slip-section">ITEM DETAILS</p>
          <table class="slip-table">
            <thead>
              <tr>
                <th>ITEM NO.</th>
                <th>PROPERTY</th>
                <th>ITEM DESCRIPTION &amp; SERIAL NO.</th>
                <th>QTY</th>
                <th>UNIT COST</th>
                <th>AMOUNT</th>
                <th>CONDITION UPON RELEASE</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, index) in lines" :key="line.item_id">
                <td>{{ index + 1 }}</td>
                <td>{{ line.item_no }}</td>
                <td>{{ line.description }}</td>
                <td>{{ line.qty }}</td>
                <td>PHP {{ formatMoney(line.unit_cost) }}</td>
                <td>PHP {{ formatMoney(line.amount) }}</td>
                <td>{{ fields.conditions[line.item_id] || '' }}</td>
              </tr>
              <tr v-if="!lines.length">
                <td colspan="7">No allocatable stock lines for this request.</td>
              </tr>
              <tr>
                <td colspan="5"><strong>TOTAL:</strong></td>
                <td><strong>PHP {{ formatMoney(total) }}</strong></td>
                <td></td>
              </tr>
            </tbody>
          </table>

          <p class="slip-section">RETURN INFORMATION (If temporary release)</p>
          <p>Expected Return Date: <u>{{ fields.expected_return_date || '_________________' }}</u></p>
          <p>Actual Return Date: <u>{{ fields.actual_return_date || '___________________' }}</u></p>

          <p class="slip-section">AUTHORIZATION &amp; ACKNOWLEDGEMENT</p>
          <p>Recipient Acknowledgement:</p>
          <p>I hereby acknowledge receipt of the above-listed items(s) in good order and condition unless otherwise stated. I accept responsibility for the proper care, safe custody, and timely return of these properties.</p>

          <div
            v-for="signatory in signatories"
            :key="signatory.key"
            class="slip-signatory"
            :class="{ 'slip-signatory-recipient': signatory.key === 'received_by' }"
          >
            <p>{{ signatory.label }}:</p>
            <p>Signature: <u>{{ fields[`${signatory.key}_signature`] || '________________' }}</u></p>
            <p>Printed Name: <u>{{ fields[`${signatory.key}_name`] || '________________' }}</u></p>
            <p>Date: <u>{{ fields[`${signatory.key}_date`] || '___________________' }}</u></p>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    docNo: { type: String, default: '' },
    documentDate: { type: String, default: '' },
    lines: { type: Array, default: () => [] },
    purposes: { type: Array, default: () => [] },
});

const fields = defineModel('fields', { required: true });

const signatories = [
    { key: 'released_by', label: 'Released By (Property Custodian)' },
    { key: 'received_by', label: 'Received By (Recipient)' },
    { key: 'approved_by', label: 'Approved By (Department Head / Supervisor)' },
];

const total = computed(() => props.lines.reduce((sum, line) => sum + Number(line.amount ?? 0), 0));

function formatMoney(value) {
    return Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<style scoped>
.slip-doc {
    background: #fff;
    color: #111;
    box-shadow: 0 1px 4px rgb(0 0 0 / 15%);
    font-size: 10px;
    line-height: 1.25;
    min-height: 100%;
    padding: 16px;
}
.slip-doc p {
    margin: 3px 0;
}
.slip-title {
    text-align: center;
    font-size: 12px;
}
.slip-section {
    font-weight: bold;
    margin: 8px 0 3px;
}
.slip-field {
    display: grid;
    gap: 4px;
    min-width: 0;
    color: #4b5563;
    font-size: 11px;
}
.slip-field input {
    min-width: 0;
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: #fff;
    padding: 7px 8px;
    color: #111827;
    font: inherit;
}
.preview-scroll {
    height: 75vh;
    overflow: auto;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #e5e7eb;
    padding: 10px;
}
.slip-preview {
    position: sticky;
    top: 1rem;
    width: 520px;
    max-width: 100%;
    align-self: start;
}
.preview-purpose {
    display: inline-block;
    margin-right: 6px;
}
.slip-table {
    border-collapse: collapse;
    width: 100%;
    margin: 4px 0;
    table-layout: fixed;
}
.slip-table th,
.slip-table td {
    border: 1px solid #111;
    overflow-wrap: anywhere;
    padding: 3px 2px;
    font-size: 8px;
    text-align: left;
    vertical-align: top;
}
.slip-signatory {
    margin-top: 6px;
}
.slip-signatory-recipient {
    margin-left: 68%;
}
@media (max-width: 1023px) {
      .slip-preview {
          position: static;
          width: 100%;
      }
      .preview-scroll {
          height: 75vh;
      }
}
</style>
