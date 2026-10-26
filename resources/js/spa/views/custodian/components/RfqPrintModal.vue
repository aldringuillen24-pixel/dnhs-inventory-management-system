<template>
  <Modal
    :open="open"
    title="Print RFQ"
    subtitle="Select disposal-eligible items and edit the request before previewing or downloading the PDF."
    max-width="max-w-7xl"
    header-class="-mx-6 -mt-6 mb-4 border-b border-gray-200/70 bg-gray-100/70 px-6 py-4 backdrop-blur-sm dark:border-gray-700/70 dark:bg-gray-800/60"
    footer-class="-mx-6 -mb-6 mt-3 border-gray-200/70 bg-gray-100/70 px-6 pb-6 pt-3 backdrop-blur-sm dark:border-gray-700/70 dark:bg-gray-800/60"
    :fixed-body="true"
    :close-on-escape="!selectionDrawerOpen"
    @close="handleClose"
  >
    <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900" @keydown.esc.window="selectionDrawerOpen = false">
      <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-2 sm:px-6 dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Document preview</h2>
        <div class="flex gap-1" aria-label="Preview format">
          <button type="button" class="rounded px-2.5 py-1 text-xs font-semibold" :class="previewMode === 'html' ? 'bg-white text-sky-700 shadow-sm dark:bg-gray-700 dark:text-sky-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'" :aria-pressed="previewMode === 'html'" @click="previewMode = 'html'">
            HTML Preview
          </button>
          <button type="button" class="rounded px-2.5 py-1 text-xs font-semibold disabled:cursor-wait disabled:opacity-60" :class="previewMode === 'pdf' ? 'bg-white text-sky-700 shadow-sm dark:bg-gray-700 dark:text-sky-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'" :aria-pressed="previewMode === 'pdf'" :disabled="previewing && !pdfUrl" @click="selectPdfPreview">
            PDF Preview
          </button>
        </div>
      </div>
      <main class="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6">
        <div v-if="previewMode === 'pdf'" class="relative h-[65vh] min-h-[24rem] overflow-hidden rounded bg-white">
          <iframe v-if="pdfUrl" :src="pdfUrl" class="h-full w-full border-0" title="RFQ PDF preview" />
          <div v-else class="flex h-full items-center justify-center px-4 text-center text-sm text-gray-500" role="status">{{ previewing ? 'Generating PDF preview…' : pdfPreviewMessage }}</div>
          <div v-if="previewing && pdfUrl" class="absolute right-2 top-2 rounded bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-900" role="status">Refreshing PDF preview…</div>
        </div>
        <article v-else class="mx-auto min-h-full max-w-5xl bg-white p-5 text-[12px] text-black shadow-sm sm:p-8">
          <header class="relative mb-5 h-[0.88in] w-full text-center opacity-60">
            <img
              :src="'/images/header/Philippine%20Education%20Department%20Header.png'"
              alt="Department of Education, Schools Division of Escalante City"
              class="absolute left-0 right-0 top-0 mx-auto block h-[0.88in] w-[40%]"
            />
          </header>

          <section class="pb-3">
            <p class="font-bold">Bids and Awards Committee</p>
            <label class="mt-2 flex items-center gap-1 font-semibold">
              <span class="shrink-0">Project Name:</span>
              <input
                v-model="metadata.project_name"
                :size="Math.max(18, metadata.project_name.length)"
                class="preview-input preview-input--project bg-yellow-100 font-semibold"
                aria-label="Project name"
              />
            </label>
            <div class="mt-3 text-center text-[10px] leading-tight">
              <p>Escalante City, Negros Occidental, Philippines</p>
              <p>Tel. Nos. - <input v-model="metadata.bac_contact_numbers" class="preview-input inline-input" aria-label="BAC contact numbers" /></p>
              <p><a class="text-blue-700 underline">Escalante.city001@deped.gov.ph</a></p>
              <p>Depedescalantecity.weebly.com</p>
            </div>
          </section>

          <section class="mt-3">
            <h1 class="text-center text-sm font-bold italic underline">Request for Quotation</h1>
            <div class="mt-2 ml-auto w-full max-w-[300px] space-y-1">
              <label class="preview-label">Date <input v-model="metadata.rfq_date" type="date" class="preview-input" /></label>
              <label class="preview-label">Quotation # <input v-model="metadata.quotation_no" class="preview-input" /></label>
              <label class="preview-label">Mode of Procurement <input v-model="metadata.procurement_mode" class="preview-input" /></label>
            </div>
          </section>

          <section class="mt-3 pt-3">
            <p class="font-semibold">To all Eligible Service Providers:</p>
            <div class="mt-1 grid grid-cols-[14px_1fr] gap-1 leading-relaxed">
              <span>I.</span>
              <p>
                Please quote your lowest price inclusive of VAT on the items listed below, subject to the Terms and Conditions of this RFQ, and submit your quotation duly signed by your representative in an envelope not later than
                <input v-model="metadata.submission_deadline" class="preview-input inline-input" placeholder="Submission deadline" />
                at DepEd, Dian-ay National High School, BAC Office, Escalante City, Negros Occidental, Philippines. For more information, contact the DNHS School-BAC Secretariat at
                <input v-model="metadata.secretariat_contact" class="preview-input inline-input" placeholder="Secretariat contact" />.
                Prospective service providers should verify the item details with the School Bids and Awards Committee at
                <input v-model="metadata.verification_contact" class="preview-input inline-input" placeholder="Verification contact" />.
              </p>
            </div>
            <div class="mt-3 text-right">
              <div class="ml-auto mb-2 h-5 w-64 border-b border-dotted border-gray-500" aria-label="BAC chair signature line" />
              <input v-model="metadata.bac_chair_name" class="preview-input ml-auto block w-64 text-right font-bold" aria-label="BAC chair name" />
              <input v-model="metadata.bac_chair_title" class="preview-input ml-auto mt-0.5 block w-64 text-right" aria-label="BAC chair title" />
            </div>
          </section>

          <section class="mt-4">
            <table class="w-full table-fixed border-collapse text-center">
              <thead>
                <tr>
                  <th class="preview-th">Inspection of Unserviceable Properties</th>
                  <th class="preview-th">Schedule of Public Auction</th>
                  <th class="preview-th">Pull-out / Taking of Awarded Items</th>
                  <th class="preview-th">Floor Price</th>
                  <th class="preview-th">Price Offered</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td class="preview-td"><textarea v-model="metadata.inspection_schedule" rows="3" class="preview-input w-full text-center" /></td>
                  <td class="preview-td"><textarea v-model="metadata.auction_schedule" rows="3" class="preview-input w-full text-center" /></td>
                  <td class="preview-td"><textarea v-model="metadata.pullout_schedule" rows="3" class="preview-input w-full text-center" /></td>
                  <td class="preview-td"><input v-model.number="metadata.floor_price" type="number" min="0" step="0.01" class="preview-input w-full text-right" /></td>
                  <td class="preview-td">&nbsp;</td>
                </tr>
                <tr>
                  <th class="preview-th">Name of Project</th>
                  <td colspan="4" class="preview-td text-left font-bold">{{ metadata.project_name }}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section class="mt-3">
            <p class="mb-1">This is to submit our price quotations as indicated above, subject to the terms and conditions of this RFQ.</p>
            <table class="w-full border-collapse">
              <tbody>
                <tr><th class="preview-th text-left">Service Provider Name</th><td colspan="3" class="preview-td"><input v-model="metadata.supplier_name" class="preview-input w-full" /></td><th class="preview-th">TIN</th><td class="preview-td"><input v-model="metadata.supplier_tin" class="preview-input w-full" /></td></tr>
                <tr><th class="preview-th text-left">Address</th><td colspan="5" class="preview-td"><input v-model="metadata.supplier_address" class="preview-input w-full" /></td></tr>
                <tr><th class="preview-th text-left">Telephone</th><td class="preview-td"><input v-model="metadata.supplier_phone" class="preview-input w-full" /></td><th class="preview-th">Fax</th><td class="preview-td"><input v-model="metadata.supplier_fax" class="preview-input w-full" /></td><th class="preview-th">E-mail</th><td class="preview-td"><input v-model="metadata.supplier_email" type="email" class="preview-input w-full" /></td></tr>
                <tr><th colspan="2" class="preview-th text-left">Representative signature / printed name</th><td colspan="2" class="preview-td"><input v-model="metadata.representative_signature" class="preview-input w-full" /></td><th class="preview-th">Date</th><td class="preview-td"><input v-model="metadata.representative_date" class="preview-input w-full" /></td></tr>
              </tbody>
            </table>
          </section>

          <section class="mt-4">
            <p class="mb-1 text-center font-bold">Instruction: Indicate “comply” or “not comply” in the space provided below.</p>
            <div class="overflow-x-auto">
              <table class="w-full min-w-[760px] border-collapse">
                <thead><tr>
                  <th class="preview-th">Items &amp; Description</th><th class="preview-th">Unit</th><th class="preview-th">Qty</th>
                  <th class="preview-th">Unit Price (ABC)</th><th class="preview-th">Price Offered</th><th class="preview-th">Compliance</th><th class="preview-th">Selection</th>
                </tr></thead>
                <tbody>
                  <tr v-for="row in selectedItems" :key="row.item_id">
                    <td class="preview-td"><input v-model="row.description" class="preview-input w-full" /></td>
                    <td class="preview-td"><input v-model="row.unit" class="preview-input w-full text-center" /></td>
                    <td class="preview-td"><input v-model.number="row.quantity" type="number" min="0" step="any" class="preview-input w-16 text-right" /></td>
                    <td class="preview-td"><input v-model.number="row.unit_price" type="number" min="0" step="0.01" class="preview-input w-24 text-right" /></td>
                    <td class="preview-td"><input v-model.number="row.price_offered" type="number" min="0" step="0.01" class="preview-input w-24 text-right" /></td>
                    <td class="preview-td p-0">
                      <div class="grid h-full grid-cols-3">
                        <div class="border-r border-black p-1"><input v-model="row.compliance" class="preview-input w-full" /></div>
                        <div class="border-r border-black p-1" />
                        <div class="p-1" />
                      </div>
                    </td>
                    <td class="preview-td"><button type="button" class="font-semibold text-rose-600" @click="toggleItemById(row.item_id, false)">Remove</button></td>
                  </tr>
                  <tr v-if="selectedItems.length === 0"><td colspan="7" class="preview-td py-4 text-center text-gray-500">Select one or more eligible items to populate this table.</td></tr>
                  <tr>
                    <th colspan="3" class="preview-th text-right">TOTAL</th>
                    <td class="preview-td text-right font-bold">{{ formatMoney(calculatedTotal) }}</td>
                    <td colspan="3" class="preview-td">
                      <label class="flex items-center justify-end gap-2 font-semibold">Editable total
                        <input v-model.number="metadata.total" type="number" min="0" step="0.01" class="preview-input w-28 text-right" @input="totalManuallyEdited = true" />
                      </label>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <section class="mt-4">
            <div class="grid grid-cols-[1fr_140px] border border-black">
              <h2 class="border-b border-black p-2 font-bold">Documentary Requirements</h2>
              <h2 class="border-b border-l border-black p-2 text-center font-bold">Compliance</h2>
              <template v-for="(requirement, index) in requirements" :key="index">
                <div class="flex items-center gap-2 border-b border-black p-1.5">
                  <span class="shrink-0">{{ index + 1 }}.</span>
                  <input v-model="requirement.description" class="preview-input w-full" />
                  <button type="button" aria-label="Remove requirement" class="px-1 font-bold text-rose-600" @click="requirements.splice(index, 1)">×</button>
                </div>
                <div class="border-b border-l border-black p-1.5"><input v-model="requirement.compliance" class="preview-input w-full" /></div>
              </template>
            </div>
            <button type="button" class="mt-2 text-xs font-semibold text-sky-700 hover:underline" @click="requirements.push({ description: '', compliance: '' })">
              + Add documentary requirement
            </button>
          </section>

          <section class="mt-6 space-y-3 text-[12px] leading-relaxed">
            <h2 class="font-bold">III. Terms and Conditions</h2>

            <div>
              <h3 class="mb-1 font-bold">A. Submission of Requirements</h3>
              <textarea v-model="terms.submission_address" rows="2" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Submission requirements paragraph 1" />
              <textarea v-model="terms.submission_documents_intro" rows="2" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Submission requirements paragraph 2" />
              <div class="ml-8">
                <textarea v-model="terms.document_mayors_permit_post_qualification" rows="2" class="preview-input w-full resize-y" aria-label="Mayor's permit requirement" />
                <textarea v-model="terms.document_mayors_permit_payment" rows="1" class="preview-input w-full resize-y" aria-label="Permit before payment requirement" />
                <textarea v-model="terms.document_philgeps" rows="1" class="preview-input w-full resize-y" aria-label="PhilGEPS requirement" />
                <textarea v-model="terms.document_dole" rows="2" class="preview-input w-full resize-y" aria-label="DOLE compliance requirement" />
              </div>
              <textarea v-model="terms.previous_submission_note" rows="2" class="preview-input w-full resize-y italic" aria-label="Previous submission note" />
            </div>

            <div>
              <h3 class="mb-1 font-bold">B. Price Validity</h3>
              <textarea v-model="terms.price_validity" rows="1" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Price validity terms" />
            </div>
            <div>
              <h3 class="mb-1 font-bold">C. Ocular Inspection</h3>
              <textarea v-model="terms.ocular_inspection" rows="1" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Ocular inspection terms" />
            </div>
            <div>
              <h3 class="mb-1 font-bold">D. Award</h3>
              <textarea v-model="terms.award" rows="2" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Award terms" />
              <p class="mt-2 font-semibold">Bids and Awards Committee</p>
              <textarea v-model="terms.award_tie_break" rows="2" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Tie-breaking terms" />
              <textarea v-model="terms.award_absent_bidder" rows="2" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Absent bidder terms" />
            </div>
            <div>
              <h3 class="mb-1 font-bold">E. Delivery of Service</h3>
              <textarea v-model="terms.delivery" rows="3" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Delivery terms" />
            </div>
            <div>
              <h3 class="mb-1 font-bold">F. Evaluation of Quotations</h3>
              <textarea v-model="terms.evaluation_intro" rows="1" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Evaluation criteria introduction" />
              <div class="ml-8">
                <textarea v-model="terms.evaluation_price" rows="1" class="preview-input w-full resize-y" aria-label="Price quotation criterion" />
                <textarea v-model="terms.evaluation_compliance" rows="1" class="preview-input w-full resize-y" aria-label="Items compliance criterion" />
                <textarea v-model="terms.evaluation_documents" rows="1" class="preview-input w-full resize-y" aria-label="Document completeness criterion" />
              </div>
              <textarea v-model="terms.evaluation_note" rows="1" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Evaluation note" />
            </div>
            <div>
              <h3 class="mb-1 font-bold">G. Instructions</h3>
              <div class="ml-4 space-y-1">
                <textarea v-model="terms.instruction_sources" rows="3" class="preview-input w-full resize-y" aria-label="Provider sources instruction" />
                <textarea v-model="terms.instruction_failure" rows="3" class="preview-input w-full resize-y" aria-label="Failure to deliver instruction" />
                <textarea v-model="terms.instruction_taxes" rows="1" class="preview-input w-full resize-y" aria-label="Taxes instruction" />
                <textarea v-model="terms.instruction_withholding" rows="2" class="preview-input w-full resize-y" aria-label="Withholding taxes instruction" />
              </div>
            </div>
            <div>
              <h3 class="mb-1 font-bold">H. Liquidated Damages</h3>
              <textarea v-model="terms.liquidated_damages" rows="2" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Liquidated damages terms" />
            </div>
            <div>
              <h3 class="mb-1 font-bold">I. Warranty</h3>
              <textarea v-model="terms.warranty" rows="1" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Warranty terms" />
            </div>
            <div>
              <h3 class="mb-1 font-bold">J. Payment</h3>
              <textarea v-model="terms.payment" rows="2" class="preview-input ml-4 w-[calc(100%-1rem)] resize-y" aria-label="Payment terms" />
            </div>
            <textarea v-model="terms.reservation" rows="2" class="preview-input w-full resize-y text-justify" aria-label="BAC reservation terms" />
          </section>

          <footer class="mt-5 w-full border-collapse border-t border-gray-400 pt-2 font-serif text-[8.5pt] leading-[1.25] text-black opacity-60">
            <table class="w-full border-collapse">
              <tbody>
                <tr>
                  <td class="w-[46px] py-0 pr-[10px] align-middle">
                    <img
                      :src="'/images/footer/escalante_city_education_seal.svg'"
                      alt="Escalante City Division Department of Education seal"
                      class="my-0 block h-auto w-9"
                    />
                  </td>
                  <td class="p-0 align-middle">
                    Escalante City, Negros Occidental, Philippines<br />
                    Tel. Nos. - (034) 724-5720, (034) 454-0746<br />
                    Escalante.city001@deped.gov.ph<br />
                    Depedescalantecity.weebly.com
                  </td>
                </tr>
              </tbody>
            </table>
          </footer>
        </article>
      </main>

      <div v-if="error" class="absolute bottom-3 left-3 right-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 shadow dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
        {{ error }}
      </div>
    </div>
    <template #footer>
      <div class="flex w-full flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedItems.length }} item(s) · Floor price {{ formatMoney(Number(metadata.floor_price || 0)) }}</p>
        <div class="flex gap-2">
          <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300" :disabled="busy" @click="handleClose">Cancel</button>
          <button type="button" class="rounded-md bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-50" :disabled="!canGenerate || busy" @click="downloadPdf">
            {{ generating ? 'Generating…' : 'Generate RFQ PDF' }}
          </button>
        </div>
      </div>
    </template>
    <template #overlay>
      <button
        type="button"
        class="absolute left-0 top-36 z-[100001] flex items-center rounded-r-md border border-l-0 border-sky-200 bg-white px-3 py-3 text-xs font-semibold text-sky-700 shadow-lg transition hover:bg-sky-50 focus:outline-none focus:ring-2 focus:ring-sky-500 dark:border-sky-500/30 dark:bg-gray-800 dark:text-sky-300 dark:hover:bg-sky-500/10"
        :aria-expanded="selectionDrawerOpen"
        :aria-label="selectionDrawerOpen ? 'Close item selection drawer' : 'Open item selection drawer'"
        aria-controls="rfq-selection-drawer"
        @click="selectionDrawerOpen = !selectionDrawerOpen"
      >
        <svg
          class="h-4 w-4 transition-transform duration-200"
          :class="{ 'rotate-180': selectionDrawerOpen }"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
          aria-hidden="true"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
        </svg>
      </button>

      <Transition name="rfq-fade">
        <div
          v-if="selectionDrawerOpen"
          class="absolute inset-0 z-[100000] rounded-md bg-gray-900/40"
          aria-hidden="true"
          @click="selectionDrawerOpen = false"
        />
      </Transition>

      <Transition name="rfq-drawer">
        <aside
          v-if="selectionDrawerOpen"
          id="rfq-selection-drawer"
          class="absolute inset-y-0 left-0 z-[100001] flex w-full max-w-sm flex-col rounded-l-md border-r border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950"
          aria-label="Select disposal-eligible items"
        >
          <header class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <div>
              <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Select disposal-eligible items</h3>
              <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ selectedItems.length }} item(s) selected</p>
            </div>
            <button
              type="button"
              class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300"
              aria-label="Close item selection drawer"
              @click="selectionDrawerOpen = false"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </header>

          <div
            v-if="loadingItems"
            class="min-h-0 flex-1 space-y-2 overflow-hidden p-3 dark:bg-gray-900/5"
            aria-label="Loading disposal-eligible items"
            aria-busy="true"
          >
            <div
              v-for="row in 4"
              :key="row"
              class="overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
            >
              <div class="flex items-center gap-2 px-3 py-2">
                <div class="h-3.5 w-3.5 animate-pulse rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800" />
                <div class="h-3 w-28 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
                <div class="ml-auto h-2.5 w-8 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
              </div>
              <div class="space-y-2 border-t border-gray-100 px-3 py-2 dark:border-gray-700">
                <div v-for="itemRow in 2" :key="itemRow" class="flex items-center gap-2">
                  <div class="h-3.5 w-3.5 animate-pulse rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800" />
                  <div class="h-2.5 w-24 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
                  <div class="ml-auto h-2.5 w-12 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
                </div>
              </div>
            </div>
          </div>
          <div v-else-if="error && eligibleByCategory.length === 0" class="m-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-center text-xs text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            {{ error }}
          </div>
          <div v-else-if="eligibleByCategory.length === 0" class="m-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
            No disposal-eligible items found.
          </div>
          <div v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3 dark:bg-gray-900/5">
            <section
              v-for="group in eligibleByCategory"
              :key="group.category_id"
              class="overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
            >
              <button
                type="button"
                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs font-semibold text-gray-800 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                @click="toggleCategory(group.category_id)"
              >
                <span class="flex items-center gap-2">
                  <input
                    type="checkbox"
                    :checked="isCategoryFullySelected(group)"
                    :indeterminate="isCategoryPartiallySelected(group)"
                    class="h-3.5 w-3.5 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                    @click.stop="toggleCategorySelection(group)"
                  />
                  <span>{{ group.category_name }}</span>
                  <span class="text-[10px] text-gray-500 dark:text-gray-400">({{ group.items.length }})</span>
                </span>
                <svg
                  class="h-3.5 w-3.5 text-gray-400 transition-transform"
                  :class="{ 'rotate-180': expandedCategories.includes(group.category_id) }"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                </svg>
              </button>
              <div v-if="expandedCategories.includes(group.category_id)" class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-800 dark:border-gray-700">
                <label
                  v-for="item in group.items"
                  :key="item.item_id"
                  class="flex cursor-pointer items-start gap-2 px-3 py-2 text-[11px] hover:bg-gray-50 dark:hover:bg-white/5"
                >
                  <input
                    type="checkbox"
                    :checked="selectedIds.includes(item.item_id)"
                    class="mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-sky-600 focus:ring-sky-500"
                    @change="toggleItem(item, $event.target.checked)"
                  />
                  <span class="min-w-0 flex-1">
                    <span class="block font-medium text-gray-900 dark:text-white">{{ item.item_name }}</span>
                    <span class="mt-0.5 block text-gray-500 dark:text-gray-400">
                      {{ item.inventory_item_no || `#${item.item_id}` }} · {{ item.quantity }} {{ item.unit || 'unit(s)' }}
                    </span>
                  </span>
                </label>
              </div>
            </section>
          </div>
        </aside>
      </Transition>
    </template>
  </Modal>

</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import api from '../../../lib/axios';
import Modal from '../../../components/ui/dialogs/Modal.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  selectedIds: { type: Array, default: () => [] },
});
const emit = defineEmits(['close']);
const selectedIds = ref([]);
const eligibleByCategory = ref([]);
const expandedCategories = ref([]);
const rowOverrides = ref({});
const loadingItems = ref(false);
const selectionDrawerOpen = ref(false);
const previewing = ref(false);
const generating = ref(false);
const pdfUrl = ref('');
const previewMode = ref('html');
const previewCurrent = ref(false);
const error = ref('');
const totalManuallyEdited = ref(false);
const floorManuallyEdited = ref(false);
let previewBlob = null;
let previewRefreshTimer = null;
let previewRequestId = 0;
const busy = computed(() => previewing.value || generating.value);

const metadata = ref({});
const requirements = ref([]);
const terms = ref({});

function resetForm() {
  const now = new Date();
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
  metadata.value = {
    quotation_no: `RFQ-${today}`,
    rfq_date: today,
    procurement_mode: 'Public Bidding',
    project_name: 'Public Bidding for one (1) Lot of Disposal of various unserviceable properties',
    bac_chair_name: 'LILIBETH L. CASTRO',
    bac_chair_title: 'Chair, Bids and Awards Committee',
    bac_contact_numbers: '(034) 724-5720, (034) 454-0746',
    submission_deadline: '',
    secretariat_contact: '(09483841228)',
    verification_contact: '(09097148630)',
    inspection_schedule: '',
    auction_schedule: '',
    pullout_schedule: '',
    supplier_name: '',
    supplier_tin: '',
    supplier_address: '',
    supplier_phone: '',
    supplier_fax: '',
    supplier_email: '',
    representative_signature: '',
    representative_date: '',
    total: 0,
    floor_price: 0,
  };
  requirements.value = [
    { description: 'Updated/Valid PHILGEPS Certificate of registration', compliance: '' },
    { description: 'Quotation sheet shall include the terms of payment, price validity, company TIN.', compliance: '' },
    { description: 'Business/Mayor’s Permit or Official Receipt as proof of renewal upon post-qualification', compliance: '' },
  ];
  terms.value = {
    submission_address: '1. Quotations and other requirements stated below shall be addressed to the Bids and Awards Committee (BAC) Chairperson c/o BAC Secretariat at Escalante City, Negros Occidental, Philippines. If quotation is sealed, please indicate in the envelope the quotation number and name of bidder.',
    submission_documents_intro: '2. In addition, you are required to submit the following necessary documents:',
    document_mayors_permit_post_qualification: 'a. Present valid Mayor’s Permit or Official Receipt as proof of renewal upon post-qualification; and,',
    document_mayors_permit_payment: 'b. Present valid Mayor’s Permit prior to payment.',
    document_philgeps: 'c. PhilGEPS Registration Number',
    document_dole: 'd. Certification re: Compliance to DOLE Rules and Regulations as to employee compensation',
    previous_submission_note: '(Note: Supplier who already submitted their documents in the previous procurement/s may no longer submit the necessary documents mentioned above.)',
    price_validity: 'Price should be valid thirty (30) calendar days after the deadline of submission of quotation.',
    ocular_inspection: 'The BAC, if necessary, may order the conduct of an ocular inspection prior to award and/or conduct of the event.',
    award: 'The provider who submitted the lowest calculated responsive quotation and who passed post qualification conducted by the BAC and end-user shall be awarded the contract.',
    award_tie_break: 'In case of tie of two or more Bidders whose bids are determined and declared as the Lowest Calculated and Responsive Bids (LCRB) having been Post-Qualified, the method to be used to break the tie is “draw lots” which is non-discretionary and non discriminatory.',
    award_absent_bidder: 'In case of non-appearance of one party, it waives the right to participate in the drawing of lots but not in the acceptance of award if declared as winner.',
    delivery: 'Delivery of Goods/s shall be on the date specified in the Request for Quotation (RFQ) or in the Purchase Order. In case the service/s shall be moved by the end-user within the validity period (30 days) the service provider is responsible to accommodate the request. On the other hand, the service provider may reject the award if the new schedule is beyond the validity period.',
    evaluation_intro: 'Quotation shall be compared and evaluated on the basis of the following criteria:',
    evaluation_price: '1. Price Quotation;',
    evaluation_compliance: '2. Compliance with Items & Description',
    evaluation_documents: '3. Completeness of documents submitted',
    evaluation_note: 'Note: (Validation of the submitted documents is done during post-qualification.)',
    instruction_sources: '1. Provider shall be responsible for the source(s) of its goods/services/equipment, which shall provide in accordance with the schedule, and specifications of the RFQ or contract. Failure of the provider to comply with this provision shall be ground for cancellation of the award or purchase order/contract issued to the provider.',
    instruction_failure: '2. Provider who accepted a contract but failed to deliver the required goods/services/equipment within the time called for in the contract shall be disqualified from participating in DepEd or any of the DepEd units’ future procurement activities. This is without prejudice to the imposition of other sanctions prescribed under R.A. 9184 and its revised IRR against the supplier.',
    instruction_taxes: '3. All duties, excise, and other taxes and revenue charges shall be paid by the provider.',
    instruction_withholding: '4. All transactions are subject to withholding of credible Value Added Tax and/or Expanded Value Added Tax per revenue regulation(s) of the Bureau of Internal Revenue.',
    liquidated_damages: 'A penalty of one-tenth of one percent (0.001) of the total value of the undelivered goods/services/equipment shall be charged as liquidated damages for every day of delay of the delivery of the purchased Goods.',
    warranty: 'Supplier warrants that all goods/services/equipment to be provided are of acceptable industry standard.',
    payment: 'One Hundred Percent (100%) may be paid to the Provider’s Account less Government Taxes after the complete delivery/ies; based on the actual completed Delivery of the Goods/Services.',
    reservation: 'The DepEd Escalante City-Bids and Awards Committee reserves the right to accept or reject any bid, to annul the bidding process, and to reject all bids at any time prior to contract award, without thereby incurring any liability to the affected bidder or bidders.',
  };
  selectedIds.value = [...new Set(props.selectedIds.map(Number))];
  totalManuallyEdited.value = false;
  floorManuallyEdited.value = false;
  selectionDrawerOpen.value = false;
  error.value = '';
}

const allEligibleItems = computed(() => eligibleByCategory.value.flatMap((group) => group.items));
const selectedItems = computed(() => selectedIds.value
  .map((id) => rowOverrides.value[id])
  .filter(Boolean));
const calculatedTotal = computed(() => selectedItems.value.reduce(
  (sum, row) => sum + (Number(row.quantity) || 0) * (Number(row.unit_price) || 0),
  0,
));
const canGenerate = computed(() => !loadingItems.value && selectedItems.value.length > 0);
const pdfPreviewMessage = computed(() => {
  if (loadingItems.value) return 'Loading eligible items…';
  if (selectedItems.value.length === 0) return 'Select at least one eligible item to generate the RFQ PDF.';
  return 'PDF preview is not available.';
});

watch(calculatedTotal, (value) => {
  if (!totalManuallyEdited.value) metadata.value.total = value;
  if (!floorManuallyEdited.value) metadata.value.floor_price = value;
}, { immediate: true });

watch(() => props.open, (isOpen) => {
  if (isOpen) {
    closePreview();
    resetForm();
    fetchEligibleItems();
  } else {
    closePreview();
  }
});

watch(
  [metadata, terms, requirements, rowOverrides, selectedIds],
  () => {
    previewCurrent.value = false;
    if (!props.open || previewMode.value !== 'pdf') return;
    clearTimeout(previewRefreshTimer);
    previewRefreshTimer = setTimeout(() => {
      previewRefreshTimer = null;
      generatePreviewPdf();
    }, 500);
  },
  { deep: true },
);

async function fetchEligibleItems() {
  loadingItems.value = true;
  eligibleByCategory.value = [];
  rowOverrides.value = {};

  try {
    const collected = [];
    for (const workspace of ['disposed', 'ready_to_dispose']) {
      let page = 1;
      let lastPage = 1;
      do {
        const { data } = await api.get('/custodian/inventory', {
          params: { workspace, [`${workspace}_page`]: page },
        });
        const pageData = data.inventoryPages?.[workspace];
        for (const group of pageData?.data ?? []) {
          for (const source of group.sourceItems ?? []) {
            if (['disposed', 'ready_to_dispose'].includes(source.status)) collected.push(source);
          }
        }
        lastPage = Number(pageData?.last_page ?? 1);
        page += 1;
      } while (page <= lastPage);
    }

    const byCategory = new Map();
    for (const source of collected) {
      const categoryId = source.category_id ?? source.category?.category_id ?? 0;
      if (!byCategory.has(categoryId)) {
        byCategory.set(categoryId, {
          category_id: categoryId,
          category_name: source.category?.category_name ?? 'Uncategorized',
          items: [],
        });
      }
      const item = {
        item_id: Number(source.item_id),
        item_name: source.item_name ?? '',
        inventory_item_no: source.inventory_item_no ?? '',
        quantity: Number(source.quantity ?? 0),
        unit: source.unit ?? '',
        unit_cost: Number(source.unit_cost ?? 0),
      };
      byCategory.get(categoryId).items.push(item);
      rowOverrides.value[item.item_id] = {
        item_id: item.item_id,
        description: item.item_name,
        unit: item.unit,
        quantity: item.quantity,
        unit_price: item.unit_cost,
        price_offered: '',
        compliance: '',
      };
    }
    eligibleByCategory.value = [...byCategory.values()]
      .sort((a, b) => a.category_name.localeCompare(b.category_name));
    const allowedIds = new Set(allEligibleItems.value.map((item) => item.item_id));
    selectedIds.value = selectedIds.value.filter((id) => allowedIds.has(id));
    expandedCategories.value = eligibleByCategory.value.map((group) => group.category_id);
  } catch (loadError) {
    console.error('Failed to load disposal-eligible RFQ items', loadError);
    error.value = 'Failed to load disposal-eligible items. Please close and reopen the RFQ editor to try again.';
  } finally {
    loadingItems.value = false;
  }
}

function toggleCategory(categoryId) {
  expandedCategories.value = expandedCategories.value.includes(categoryId)
    ? expandedCategories.value.filter((id) => id !== categoryId)
    : [...expandedCategories.value, categoryId];
}

function isCategoryFullySelected(group) {
  return group.items.length > 0 && group.items.every((item) => selectedIds.value.includes(item.item_id));
}

function isCategoryPartiallySelected(group) {
  return group.items.some((item) => selectedIds.value.includes(item.item_id)) && !isCategoryFullySelected(group);
}

function toggleCategorySelection(group) {
  const groupIds = group.items.map((item) => item.item_id);
  selectedIds.value = isCategoryFullySelected(group)
    ? selectedIds.value.filter((id) => !groupIds.includes(id))
    : [...new Set([...selectedIds.value, ...groupIds])];
}

function toggleItem(item, checked) {
  toggleItemById(item.item_id, checked);
}

function toggleItemById(itemId, checked) {
  selectedIds.value = checked
    ? [...new Set([...selectedIds.value, Number(itemId)])]
    : selectedIds.value.filter((id) => id !== Number(itemId));
}

function formatMoney(value) {
  return Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function payload() {
  return {
    ...metadata.value,
    total: Number(metadata.value.total || 0),
    floor_price: Number(metadata.value.floor_price || 0),
    selected_ids: selectedIds.value,
    overrides: Object.fromEntries(selectedItems.value.map((row) => [row.item_id, { ...row }])),
    requirements: requirements.value,
    terms: terms.value,
  };
}

async function requestPdf() {
  const response = await api.post('/custodian/inventory/rfq-pdf', payload(), {
    responseType: 'blob',
    skipToast: true,
  });
  return new Blob([response.data], { type: 'application/pdf' });
}

async function showRequestError(requestError) {
  const responseData = requestError?.response?.data;
  if (responseData instanceof Blob) {
    try {
      const details = JSON.parse(await responseData.text());
      error.value = details.message ?? Object.values(details.errors ?? {}).flat()[0] ?? 'Failed to generate RFQ PDF.';
      return;
    } catch {
      error.value = 'Failed to generate RFQ PDF. Please try again.';
      return;
    }
  }
  error.value = responseData?.message ?? 'Failed to generate RFQ PDF. Please try again.';
}

async function generatePreviewPdf() {
  if (!canGenerate.value || generating.value) return;
  const requestId = ++previewRequestId;
  previewing.value = true;
  error.value = '';
  try {
    const blob = await requestPdf();
    if (requestId !== previewRequestId) return;
    const previousUrl = pdfUrl.value;
    previewBlob = blob;
    pdfUrl.value = URL.createObjectURL(blob);
    if (previousUrl) URL.revokeObjectURL(previousUrl);
    previewCurrent.value = true;
  } catch (requestError) {
    if (requestId === previewRequestId) {
      await showRequestError(requestError);
    }
  } finally {
    if (requestId === previewRequestId) previewing.value = false;
  }
}

function selectPdfPreview() {
  previewMode.value = 'pdf';
  if (!previewCurrent.value && canGenerate.value) generatePreviewPdf();
}

async function downloadPdf() {
  if (!canGenerate.value || busy.value) return;
  generating.value = true;
  error.value = '';
  try {
    const blob = await requestPdf();
    downloadBlob(blob);
  } catch (requestError) {
    await showRequestError(requestError);
  } finally {
    generating.value = false;
  }
}

function downloadBlob(blob) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `RFQ-${metadata.value.rfq_date || new Date().toISOString().slice(0, 10)}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
}

function closePreview() {
  clearTimeout(previewRefreshTimer);
  previewRefreshTimer = null;
  previewRequestId += 1;
  previewMode.value = 'html';
  previewCurrent.value = false;
  previewing.value = false;
  if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value);
  pdfUrl.value = '';
  previewBlob = null;
}

function handleClose() {
  if (busy.value) return;
  selectionDrawerOpen.value = false;
  closePreview();
  emit('close');
}

onUnmounted(closePreview);

</script>

<style scoped>
.preview-input {
  min-width: 0;
  border: 0;
  border-bottom: 1px dotted #9ca3af;
  background: transparent;
  padding: 2px 3px;
  color: inherit;
  font: inherit;
}

.preview-input:focus {
  border-bottom: 1px solid #0284c7;
  outline: none;
}

.preview-input--plain {
  border-bottom-color: transparent;
}

.preview-input--project,
.preview-input--project:focus {
  border-bottom: 1px dotted #9ca3af;
  flex: 0 1 auto;
  width: auto;
}

.preview-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  font-weight: 600;
}

.preview-label .preview-input {
  width: 150px;
  text-align: left;
  font-weight: 400;
}

.inline-input {
  display: inline-block;
  width: 135px;
}

.preview-th,
.preview-td {
  border: 1px solid #111;
  padding: 4px;
  vertical-align: middle;
}

.preview-th {
  font-weight: 700;
}

.rfq-drawer-enter-active,
.rfq-drawer-leave-active {
  transition: transform 0.3s ease;
}

.rfq-drawer-enter-from,
.rfq-drawer-leave-to {
  transform: translateX(-100%);
}

.rfq-fade-enter-active,
.rfq-fade-leave-active {
  transition: opacity 0.25s ease;
}

.rfq-fade-enter-from,
.rfq-fade-leave-to {
  opacity: 0;
}
</style>
