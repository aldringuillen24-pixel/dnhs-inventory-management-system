<template>
  <div
    ref="measureContainer"
    aria-hidden="true"
    style="position: absolute; left: -10000px; top: 0; visibility: hidden; pointer-events: none;"
  >
    <article class="itb-measure-page flex flex-col bg-white p-6 text-[14px] leading-relaxed text-black" style="width: 210mm; height: 297mm; box-sizing: border-box; font-family: Calibri, Arial, sans-serif;">
      <header data-itb-header-first class="mb-4 text-center">
        <img :src="'/images/header/Philippine%20Education%20Department%20Header.png'" alt="" class="mx-auto mb-3 block h-auto w-2/5 max-w-full object-contain" @load="refreshPreviewPages" />
        <hr class="my-3 border-0 border-t border-gray-400" />
        <p class="text-[16px] font-bold uppercase tracking-wide">INVITATION TO BID</p>
        <p class="text-[14px] font-semibold">Disposal of Unserviceable Properties Through Public Bidding</p>
      </header>
      <header data-itb-header-continuation class="mb-4 text-center">
        <img :src="'/images/header/Philippine%20Education%20Department%20Header.png'" alt="" class="mx-auto mb-3 block h-auto w-2/5 max-w-full object-contain" @load="refreshPreviewPages" />
        <hr class="my-3 border-0 border-t border-gray-400" />
      </header>
      <div data-itb-intro class="mt-3 flex items-start gap-3 text-justify">
        <span class="shrink-0">1.</span>
        <p>The Department of Education (DepEd) Schools Division of Escalante City, invites all interested parties to participate in the <strong><u>{{ previewMetadata.bid_description }}</u></strong> as indicated below:</p>
      </div>
      <div data-itb-table class="mt-3 overflow-x-auto">
        <table class="w-full border-collapse border border-black text-left text-[11px]">
          <thead data-itb-table-head>
            <tr>
              <th class="border border-black bg-white px-2 py-1.5 font-semibold">Item No.</th>
              <th class="border border-black bg-white px-2 py-1.5 font-semibold">Item Description</th>
              <th class="border border-black bg-white px-2 py-1.5 font-semibold text-right">Quantity</th>
              <th class="border border-black bg-white px-2 py-1.5 font-semibold text-right">Floor Price</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in selectedPreviewItems" :key="`measure-${item.item_id}`" data-itb-row>
              <td class="border border-black px-2 py-1.5">{{ index + 1 }}</td>
              <td class="border border-black px-2 py-1.5">{{ item.item_name }}</td>
              <td class="border border-black px-2 py-1.5 text-right">{{ item.quantity }}</td>
              <td class="border border-black px-2 py-1.5 text-right"><input v-model.number="item.floor_price" type="number" min="0" step="0.01" class="w-full border-b border-dotted border-black bg-transparent text-right outline-none" /></td>
            </tr>
            <tr v-if="selectedPreviewItems.length === 0" data-itb-empty-row>
              <td colspan="4" class="border border-black px-2 py-3 text-center text-black">Select disposal-eligible items in the drawer.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div data-itb-details class="mt-4 space-y-3 text-justify leading-relaxed">
        <p data-itb-detail="submission"><strong>2.</strong> Submit your Bid form with your name, signature, company name, address, and contact numbers. Submit the duly accomplished quotation form on or before <input v-model="previewMetadata.submission_deadline" type="date" aria-label="Submission deadline" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-center text-[11px]" /> at {{ previewMetadata.school_name }}, {{ previewMetadata.school_address }}, and look for <span>{{ previewMetadata.secretariat_name }}</span>.</p>
        <div data-itb-detail="requirements" class="flex items-start gap-3">
          <strong class="shrink-0">3.</strong>
          <div>
            <p>Attach to your Bid either the following:</p>
            <ul class="ml-6 list-disc">
              <li>{{ previewMetadata.corporation_requirement }}</li>
              <li>{{ previewMetadata.proprietorship_requirement }}</li>
              <li>{{ previewMetadata.business_permit_requirement }}</li>
              <li v-for="(requirement, index) in previewMetadata.additional_requirements" :key="`measure-requirement-${index}`">{{ requirement }}</li>
            </ul>
          </div>
        </div>
        <p data-itb-detail="disqualification"><strong>4.</strong> Unsigned Bid and Bid lower than the floor price shall automatically be disqualified.</p>
        <p data-itb-detail="forms"><strong>5.</strong> A complete set of Bid Form may be acquired by interested bidder from <input v-model="previewMetadata.form_acquisition_from" type="date" aria-label="Bid form availability start date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" /> to <input v-model="previewMetadata.form_acquisition_to" type="date" aria-label="Bid form availability end date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" /> at School Head office, {{ previewMetadata.school_name }}.</p>
        <div data-itb-detail="contacts" class="flex items-start gap-3">
          <strong class="shrink-0">6.</strong>
          <div>
            <p>For further information, please refer to:</p>
            <p class="mt-2"><strong>{{ previewMetadata.secretariat_name }}</strong><br />{{ previewMetadata.secretariat_role }}<br />{{ previewMetadata.secretariat_role_detail }}<br />{{ previewMetadata.contact_numbers }}</p>
          </div>
        </div>
        <p data-itb-detail="inspection"><strong>7.</strong> Inspection of Unserviceable Properties are available for inspection on <input v-model="previewMetadata.inspection_day" type="date" aria-label="Inspection date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" /> @ <input v-model="previewMetadata.inspection_time" type="text" aria-label="Inspection time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" />, {{ previewMetadata.school_name }}.</p>
        <div data-itb-detail="auction">
          <p><strong>8.</strong> Schedule of Public Auction:</p>
          <p class="pl-6">The public auction shall be conducted on an “as is, where is” basis on <input v-model="previewMetadata.auction_date" type="date" aria-label="Auction date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" /> at <input v-model="previewMetadata.auction_time" type="text" aria-label="Auction time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" />, {{ previewMetadata.school_name }}.</p>
        </div>
        <div data-itb-detail="bidSubmission">
          <p><strong>9.</strong> Submission of Bid:</p>
          <p class="pl-6">The Bid must be submitted in three <input v-model="previewMetadata.bid_submission_copies" type="text" size="1" aria-label="Bid submission copy details" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px]" :style="{ width: `${Math.max(previewMetadata.bid_submission_copies.length, 1)}ch` }" /> using the prescribed Bid Form.</p>
        </div>
        <p data-itb-detail="bidDeadline" class="text-left"><strong>10.</strong> Deadline of submission of Bid is on <input v-model="previewMetadata.submission_deadline" type="date" aria-label="Bid submission deadline date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" /> <input v-model="previewMetadata.submission_time" type="text" aria-label="Bid submission deadline time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" />. Bid submitted beyond said deadline shall not be accepted.</p>
        <p data-itb-detail="openingBids" class="text-left"><strong>11.</strong> Opening of Bids at <input v-model="previewMetadata.bid_opening_date" type="date" aria-label="Bid opening date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" /> <input v-model="previewMetadata.bid_opening_time" type="text" aria-label="Bid opening time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px]" /> in the presence of BAC and Division.</p>
        <p data-itb-detail="disposalCommittee"><strong>12.</strong> Disposal Committee.</p>
        <div data-itb-detail="awardingBids">
          <p><strong>13.</strong> Awarding of Bids:</p>
          <p class="pl-6">Award shall be made to the highest bidder.</p>
        </div>
        <div data-itb-detail="pulloutItems">
          <p><strong>14.</strong> Pull-out/Taking of Awarded Items:</p>
          <p class="pl-6">The winning bidder shall be given <input v-model="previewMetadata.pullout_period" type="text" size="1" aria-label="Pull-out period" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px]" :style="{ width: `${Math.max(previewMetadata.pullout_period.length, 1)}ch` }" /> from receipt of payment to pull out from the Old Division Office as stated in the ITB. The unserviceable items awarded subject to the supervision of Division Supply Officer, Division Accountant, Representative from the Division Inventory and Disposal Committee, witnessing and approval by the Schools Division Superintendent and COA official during the actual disposal.</p>
        </div>
        <table data-itb-detail="signatories" style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 56px; font-size: 11pt; line-height: 1.35; text-align: left;">
          <tbody>
            <tr v-for="(row, rowIndex) in [[0, 1], [2, 3], [4, 5]]" :key="`measure-signatory-row-${rowIndex}`" :style="rowIndex === 0 ? 'height: 120px;' : rowIndex === 1 ? 'height: 90px;' : ''">
              <td v-for="index in row" :key="`measure-signatory-${index}`" style="width: 50%; padding: 0; vertical-align: top;">
                <input v-model="previewMetadata.signatories[index].name" type="text" size="1" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 font-semibold" :style="{ width: `${Math.max(previewMetadata.signatories[index].name.length * 0.72 + 2, 1)}em` }" />
                <input v-model="previewMetadata.signatories[index].title" type="text" size="1" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0" :style="{ width: `${previewMetadata.signatories[index].title.length + 1}ch` }" />
                <input v-if="previewMetadata.signatories[index].role" v-model="previewMetadata.signatories[index].role" type="text" size="1" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0" :style="{ width: `${previewMetadata.signatories[index].role.length + 1}ch` }" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer data-itb-footer class="mt-auto grid grid-cols-[45%_55%] items-center border-t border-gray-500 py-2 text-[11px] leading-tight">
        <div class="flex items-center gap-2">
          <img :src="'/images/footer/image.png'" alt="" class="block h-auto w-[150px] max-w-[calc(100%-50px)]" @load="refreshPreviewPages" />
          <img :src="'/images/footer/escalante_city_education_seal.svg'" alt="" class="block h-auto w-[42px] shrink-0" @load="refreshPreviewPages" />
        </div>
        <address class="not-italic">Escalante City, Negros Occidental, Philippines<br />Tel. Nos. - (034) 724-5720, (034) 454-0746<br />escalante.city001@deped.gov.ph<br />Depedescalantecity.weebly.com</address>
      </footer>
    </article>
  </div>

  <Modal
    :open="open"
    title="Print ITB"
    subtitle="Invitation to Bid for disposal of unserviceable properties"
    max-width="max-w-7xl"
    :fixed-body="true"
    :close-on-escape="!selectionDrawerOpen"
    @close="handleClose"
  >
    <div class="itb-modal-shell relative flex h-[70vh] min-h-0 flex-1 flex-col overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900" @keydown.esc.window="selectionDrawerOpen = false">
      <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-2 dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Document preview</h2>
        <div class="flex gap-1" aria-label="Preview format">
          <button
            type="button"
            class="rounded px-2.5 py-1 text-xs font-semibold"
            :class="previewMode === 'html' ? 'bg-white text-amber-700 shadow-sm dark:bg-gray-700 dark:text-amber-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'"
            :aria-pressed="previewMode === 'html'"
            @click="previewMode = 'html'"
          >
            HTML Preview
          </button>
          <button
            type="button"
            class="rounded px-2.5 py-1 text-xs font-semibold disabled:cursor-not-allowed disabled:opacity-60"
            :class="previewMode === 'pdf' ? 'bg-white text-amber-700 shadow-sm dark:bg-gray-700 dark:text-amber-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'"
            :aria-pressed="previewMode === 'pdf'"
            :disabled="pdfPreviewLoading && !pdfPreviewUrl"
            @click="selectPdfPreview"
          >
            PDF Preview
          </button>
        </div>
      </div>

      <main class="itb-preview-main min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto p-4 sm:p-6">
        <div v-if="previewMode === 'pdf'" class="relative h-[65vh] min-h-[24rem] overflow-hidden rounded bg-white">
          <iframe v-if="pdfPreviewUrl" :src="pdfPreviewUrl" class="h-full w-full border-0" title="ITB PDF preview" />
          <div v-else class="flex h-full items-center justify-center px-4 text-center text-sm text-gray-500" role="status">
            {{ previewing ? 'Generating PDF preview…' : pdfPreviewMessage }}
          </div>
          <div v-if="previewing && pdfPreviewUrl" class="absolute right-2 top-2 rounded bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-900" role="status">
            Refreshing PDF preview…
          </div>
          <div v-if="pdfPreviewError" class="absolute bottom-2 left-2 right-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 shadow dark:border-rose-500/30 dark:bg-gray-900 dark:text-rose-300" role="alert">
            {{ pdfPreviewError }}
          </div>
        </div>

        <div v-else>
          <div class="mx-auto mb-3 flex max-w-5xl items-center justify-between text-xs text-gray-600 dark:text-gray-300">
            <span>{{ selectedPreviewItems.length }} item(s) selected for the invitation</span>
          </div>

          <div class="itb-preview-pages mx-auto flex max-w-5xl flex-col items-center gap-4">
            <article
              v-for="(previewPage, pageIndex) in previewPages"
              :key="`itb-preview-page-${pageIndex}`"
              class="itb-preview-page flex w-full max-w-[210mm] flex-col bg-white p-6 text-[14px] leading-relaxed text-black shadow-sm"
              style="width: 210mm; max-width: 100%; height: 297mm; min-height: 297mm; box-sizing: border-box; font-family: Calibri, Arial, sans-serif;"
            >
              <header
                class="mb-4 text-center"
                :data-itb-header-first="pageIndex === 0 ? '' : null"
                :data-itb-header-continuation="pageIndex > 0 ? '' : null"
              >
                <img
                  :src="'/images/header/Philippine%20Education%20Department%20Header.png'"
                  alt="Department of Education, Schools Division of Escalante City"
                  class="mx-auto mb-3 block h-auto w-2/5 max-w-full object-contain"
                />
                <hr class="my-3 border-0 border-t border-gray-400" />
                <template v-if="pageIndex === 0">
                  <p class="text-[16px] font-bold uppercase tracking-wide">INVITATION TO BID</p>
                  <p class="mt-1 text-[14px] font-semibold">Disposal of Unserviceable Properties Through Public Bidding</p>
                </template>
              </header>

              <div
                v-if="pageIndex === 0"
                data-itb-intro
                class="mt-3 flex items-start gap-3 text-justify"
              >
                  <span class="shrink-0">1.</span>
                  <p>
                    The Department of Education (DepEd) Schools Division of Escalante City, invites all interested parties to participate in the
                    <strong><span contenteditable="true" role="textbox" aria-label="ITB bidding description" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="previewMetadata.bid_description = $event.currentTarget.textContent ?? ''">{{ previewMetadata.bid_description }}</span></strong>
                    as indicated below:
                  </p>
              </div>

              <div v-if="previewPage.type === 'items'" data-itb-table class="mt-3 overflow-x-auto">
                  <table class="w-full border-collapse border border-black text-left text-[11px]">
                      <thead data-itb-table-head>
                      <tr>
                        <th class="border border-black bg-white px-2 py-1.5 font-semibold">Item No.</th>
                        <th class="border border-black bg-white px-2 py-1.5 font-semibold">Item Description</th>
                        <th class="border border-black bg-white px-2 py-1.5 font-semibold text-right">Quantity</th>
                        <th class="border border-black bg-white px-2 py-1.5 font-semibold text-right">Floor Price</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, index) in previewPage.items" :key="item.item_id" data-itb-row class="itb-preview-row">
                        <td class="border border-black px-2 py-1.5">{{ previewPage.startIndex + index + 1 }}</td>
                        <td class="border border-black px-2 py-1.5">{{ item.item_name }}</td>
                        <td class="border border-black px-2 py-1.5 text-right">{{ item.quantity }}</td>
                        <td class="border border-black px-2 py-1.5 text-right">
                          <input v-model.number="item.floor_price" type="number" min="0" step="0.01" :aria-label="`Floor price for ${item.item_name}`" class="w-full border-b border-dotted border-black bg-transparent text-right outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" />
                        </td>
                      </tr>
                      <tr v-if="previewPage.items.length === 0">
                        <td colspan="4" class="border border-black px-2 py-3 text-center text-black">No items selected for the ITB.</td>
                      </tr>
                    </tbody>
                  </table>
              </div>

              <section v-if="previewPage.details.length" data-itb-details class="mt-4 space-y-3 text-justify leading-relaxed">
                <p v-if="previewPage.details.includes('submission')" data-itb-detail="submission">
                  <strong>2.</strong> Submit your Bid form with your name, signature, company name, address, and contact numbers. Submit the duly accomplished quotation form on or before
                  <input v-model="previewMetadata.submission_deadline" type="date" class="w-32 border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-center text-[11px] outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" aria-label="Submission deadline" />
                  at {{ previewMetadata.school_name }}, {{ previewMetadata.school_address }}, and look for <span contenteditable="true" role="textbox" aria-label="Secretariat name" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="previewMetadata.secretariat_name = $event.currentTarget.textContent ?? ''">{{ previewMetadata.secretariat_name }}</span>.
                </p>

                <div v-if="previewPage.details.includes('requirements')" data-itb-detail="requirements" class="flex items-start gap-3">
                  <strong class="shrink-0">3.</strong>
                  <div>
                    <p>Attach to your Bid either the following:</p>
                    <ul class="ml-6 list-disc">
                      <li><span contenteditable="true" role="textbox" aria-label="Corporation registration requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="previewMetadata.corporation_requirement = $event.currentTarget.textContent ?? ''">{{ previewMetadata.corporation_requirement }}</span></li>
                      <li><span contenteditable="true" role="textbox" aria-label="Sole proprietorship registration requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="previewMetadata.proprietorship_requirement = $event.currentTarget.textContent ?? ''">{{ previewMetadata.proprietorship_requirement }}</span></li>
                      <li><span contenteditable="true" role="textbox" aria-label="Business permit requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="previewMetadata.business_permit_requirement = $event.currentTarget.textContent ?? ''">{{ previewMetadata.business_permit_requirement }}</span></li>
                      <li v-for="(requirement, index) in previewMetadata.additional_requirements" :key="`itb-requirement-${index}`">
                        <input v-model="previewMetadata.additional_requirements[index]" type="text" :aria-label="`Additional requirement ${index + 1}`" class="inline-block w-[calc(100%-2rem)] border-0 border-b border-dotted border-black bg-transparent p-0 align-middle outline-none focus:border-solid focus:border-amber-600" />
                        <button type="button" class="float-right inline-flex h-5 w-5 items-center justify-center rounded-full border-0 text-gray-500 hover:bg-rose-50 hover:text-rose-600 focus:border-0 focus:outline-none focus:ring-0" :aria-label="`Remove additional requirement ${index + 1}`" title="Remove requirement" @click="removeRequirement(index)">
                          <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" /></svg>
                        </button>
                      </li>
                    </ul>
                    <button type="button" class="mt-1 inline-flex items-center gap-1 rounded border-0 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50 focus:border-0 focus:outline-none focus:ring-0" aria-label="Add requirement" title="Add requirement" @click="addRequirement">
                      <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z" /></svg>
                      Add requirement
                    </button>
                  </div>
                </div>

                <p v-if="previewPage.details.includes('disqualification')" data-itb-detail="disqualification"><strong>4.</strong> Unsigned Bid and Bid lower than the floor price shall automatically be disqualified.</p>
                <p v-if="previewPage.details.includes('forms')" data-itb-detail="forms"><strong>5.</strong> A complete set of Bid Form may be acquired by interested bidder from <input v-model="previewMetadata.form_acquisition_from" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid form availability start date" /> to <input v-model="previewMetadata.form_acquisition_to" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid form availability end date" /> at School Head office, {{ previewMetadata.school_name }}.</p>

                <div v-if="previewPage.details.includes('contacts')" data-itb-detail="contacts" class="flex items-start gap-3">
                  <strong class="shrink-0">6.</strong>
                  <div class="min-w-0 flex-1">
                    <p>For further information, please refer to:</p>
                    <p class="mt-2">
                      <input v-model="previewMetadata.secretariat_name" type="text" size="1" aria-label="Secretariat name" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 font-semibold outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(previewMetadata.secretariat_name.length, 1)}ch` }" />
                      <input v-model="previewMetadata.secretariat_role" type="text" size="1" aria-label="Secretariat role" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(previewMetadata.secretariat_role.length, 1)}ch` }" />
                      <input v-model="previewMetadata.secretariat_role_detail" type="text" size="1" aria-label="Secretariat role detail" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(previewMetadata.secretariat_role_detail.length, 1)}ch` }" />
                      <input v-model="previewMetadata.contact_numbers" type="text" size="1" aria-label="Contact numbers" class="mt-1 block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(previewMetadata.contact_numbers.length, 1)}ch` }" />
                    </p>
                  </div>
                </div>

                <p v-if="previewPage.details.includes('inspection')" data-itb-detail="inspection"><strong>7.</strong> Inspection of Unserviceable Properties are available for inspection on <input v-model="previewMetadata.inspection_day" type="date" aria-label="Inspection date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> @ <input v-model="previewMetadata.inspection_time" type="text" aria-label="Inspection time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" />, {{ previewMetadata.school_name }}.</p>
                <div v-if="previewPage.details.includes('auction')" data-itb-detail="auction">
                  <p><strong>8.</strong> Schedule of Public Auction:</p>
                  <p class="pl-6">The public auction shall be conducted on an “as is, where is” basis on <input v-model="previewMetadata.auction_date" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Auction date" /> at <input v-model="previewMetadata.auction_time" type="text" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Auction time" />, {{ previewMetadata.school_name }}.</p>
                </div>
                <div v-if="previewPage.details.includes('bidSubmission')" data-itb-detail="bidSubmission">
                  <p><strong>9.</strong> Submission of Bid:</p>
                  <p class="pl-6">The Bid must be submitted in three <input v-model="previewMetadata.bid_submission_copies" type="text" size="1" aria-label="Bid submission copy details" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px] outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(previewMetadata.bid_submission_copies.length, 1)}ch` }" /> using the prescribed Bid Form.</p>
                </div>
                <p v-if="previewPage.details.includes('bidDeadline')" data-itb-detail="bidDeadline" class="text-left"><strong>10.</strong> Deadline of submission of Bid is on <input v-model="previewMetadata.submission_deadline" type="date" aria-label="Bid submission deadline date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> <input v-model="previewMetadata.submission_time" type="text" aria-label="Bid submission deadline time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" />. Bid submitted beyond said deadline shall not be accepted.</p>
                <p v-if="previewPage.details.includes('openingBids')" data-itb-detail="openingBids" class="text-left"><strong>11.</strong> Opening of Bids at <input v-model="previewMetadata.bid_opening_date" type="date" aria-label="Bid opening date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> <input v-model="previewMetadata.bid_opening_time" type="text" aria-label="Bid opening time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> in the presence of BAC and Division.</p>
                <p v-if="previewPage.details.includes('disposalCommittee')" data-itb-detail="disposalCommittee"><strong>12.</strong> Disposal Committee.</p>
                <div v-if="previewPage.details.includes('awardingBids')" data-itb-detail="awardingBids">
                  <p><strong>13.</strong> Awarding of Bids:</p>
                  <p class="pl-6">Award shall be made to the highest bidder.</p>
                </div>
                <div v-if="previewPage.details.includes('pulloutItems')" data-itb-detail="pulloutItems">
                  <p><strong>14.</strong> Pull-out/Taking of Awarded Items:</p>
                  <p class="pl-6">The winning bidder shall be given <input v-model="previewMetadata.pullout_period" type="text" size="1" aria-label="Pull-out period" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px] outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(previewMetadata.pullout_period.length, 1)}ch` }" /> from receipt of payment to pull out from the Old Division Office as stated in the ITB. The unserviceable items awarded subject to the supervision of Division Supply Officer, Division Accountant, Representative from the Division Inventory and Disposal Committee, witnessing and approval by the Schools Division Superintendent and COA official during the actual disposal.</p>
                </div>
                <table v-if="previewPage.details.includes('signatories')" data-itb-detail="signatories" style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 56px; font-size: 11pt; line-height: 1.35; text-align: left;">
                  <tbody>
                    <tr v-for="(row, rowIndex) in [[0, 1], [2, 3], [4, 5]]" :key="`preview-signatory-row-${rowIndex}`" :style="rowIndex === 0 ? 'height: 120px;' : rowIndex === 1 ? 'height: 90px;' : ''">
                      <td v-for="index in row" :key="`preview-signatory-${index}`" style="width: 50%; padding: 0; vertical-align: top;">
                        <input v-model="previewMetadata.signatories[index].name" type="text" size="1" :aria-label="`Signatory ${index + 1} name`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 font-semibold outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(previewMetadata.signatories[index].name.length * 0.72 + 2, 1)}em` }" />
                        <input v-model="previewMetadata.signatories[index].title" type="text" size="1" :aria-label="`Signatory ${index + 1} title`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${previewMetadata.signatories[index].title.length + 1}ch` }" />
                        <input v-if="previewMetadata.signatories[index].role" v-model="previewMetadata.signatories[index].role" type="text" size="1" :aria-label="`Signatory ${index + 1} role`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${previewMetadata.signatories[index].role.length + 1}ch` }" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </section>
              <footer data-itb-footer class="mt-auto grid grid-cols-[45%_55%] items-center border-t border-gray-500 py-2 text-[11px] leading-tight">
                <div class="flex items-center gap-2">
                  <img :src="'/images/footer/image.png'" alt="Department of Education and Bagong Pilipinas logos" class="block h-auto w-[150px] max-w-[calc(100%-50px)]" />
                  <img :src="'/images/footer/escalante_city_education_seal.svg'" alt="Escalante City Division Department of Education seal" class="block h-auto w-[42px] shrink-0" />
                </div>
                <address class="not-italic">
                  Escalante City, Negros Occidental, Philippines<br />
                  Tel. Nos. - (034) 724-5720, (034) 454-0746<br />
                  <a class="underline" href="mailto:escalante.city001@deped.gov.ph">escalante.city001@deped.gov.ph</a><br />
                  Depedescalantecity.weebly.com
                </address>
              </footer>
            </article>
          </div>
        </div>
      </main>

    </div>
    <template #footer>
      <div class="itb-preview-controls flex justify-end gap-2">
        <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" @click="handleClose">Close</button>
        <button v-if="previewMode === 'pdf'" type="button" class="rounded-md border border-amber-200 bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-500/30" :disabled="!pdfPreviewUrl || !pdfPreviewCurrent || pdfPreviewLoading" @click="downloadPdf">Download PDF</button>
        <button v-if="previewMode === 'html'" type="button" class="rounded-md border border-amber-200 bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-500/30" :disabled="!selectedIds.length" @click="printDocument">Print ITB</button>
      </div>
    </template>
    <template #overlay>
      <button
        type="button"
        class="absolute left-0 top-36 z-[100001] flex items-center rounded-r-md border border-l-0 border-amber-200 bg-white px-3 py-3 text-xs font-semibold text-amber-700 shadow-lg transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-500 dark:border-amber-500/30 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-500/10"
        :aria-expanded="selectionDrawerOpen"
        :aria-label="selectionDrawerOpen ? 'Close ITB category drawer' : 'Open ITB category drawer'"
        aria-controls="itb-category-selection-drawer"
        @click="selectionDrawerOpen = !selectionDrawerOpen"
      >
        <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': selectionDrawerOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
        </svg>
      </button>
      <Transition name="iirup-fade">
        <div
          v-if="selectionDrawerOpen"
          class="absolute inset-0 z-[100000] rounded-md bg-gray-900/40"
          aria-hidden="true"
          @click="selectionDrawerOpen = false"
        />
      </Transition>
      <Transition name="iirup-drawer">
        <aside
          v-if="selectionDrawerOpen"
          id="itb-category-selection-drawer"
          class="absolute inset-y-0 left-0 z-[100001] flex w-full max-w-sm flex-col rounded-l-md border-r border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950"
          aria-label="Select disposal-eligible items for ITB"
        >
          <header class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <div>
              <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Select disposal-eligible items</h3>
              <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ selectedIds.length }} item(s) selected</p>
            </div>
            <button type="button" class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300" aria-label="Close ITB category drawer" @click="selectionDrawerOpen = false">
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </header>
          <div v-if="itemsLoading" class="flex min-h-0 flex-1 items-center justify-center p-6 text-center text-sm text-gray-500 dark:text-gray-400" role="status">
            Loading disposal-eligible items…
          </div>
          <div v-else-if="itemsError" class="m-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300" role="alert">
            <p>{{ itemsError }}</p>
            <button type="button" class="mt-3 rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700" @click="loadEligibleItems">Retry</button>
          </div>
          <div v-else-if="categoryGroups.length === 0" class="m-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-center text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            No categories are available and no disposal-eligible items were found.
          </div>
          <div v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
            <div v-for="group in categoryGroups" :key="group.category_id" class="rounded-md border border-gray-200 dark:border-gray-700">
              <div class="flex items-center gap-2 px-3 py-2">
                <input
                  type="checkbox"
                  :checked="isCategoryFullySelected(group)"
                  :indeterminate="isCategoryPartiallySelected(group)"
                  :aria-label="`Select all eligible items in ${group.category_name}`"
                  class="h-3.5 w-3.5 rounded-md border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-800"
                  @change="toggleCategory(group)"
                />
                <button type="button" class="flex min-w-0 flex-1 items-center justify-between gap-2 text-left text-xs font-semibold text-gray-800 dark:text-gray-200" :aria-expanded="expandedCategories.includes(group.category_id)" @click="toggleExpandedCategory(group.category_id)">
                  <span class="truncate">{{ group.category_name }}</span>
                  <span class="shrink-0 text-[10px] font-normal text-gray-500 dark:text-gray-400">{{ group.items.length }} eligible</span>
                </button>
              </div>
              <div v-if="expandedCategories.includes(group.category_id)" class="border-t border-gray-100 dark:border-gray-700">
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                  <li v-for="item in group.items" :key="item.item_id" class="flex items-start gap-2 px-3 py-2">
                    <input type="checkbox" :checked="selectedIds.includes(item.item_id)" :aria-label="`Select ${item.item_name}`" class="mt-0.5 h-3.5 w-3.5 rounded-md border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-800" @change="toggleItem(item.item_id, $event.target.checked)" />
                    <span class="min-w-0 flex-1">
                      <span class="block text-xs font-medium text-gray-800 dark:text-gray-100">{{ item.item_name }}</span>
                      <span class="mt-0.5 block text-[11px] text-gray-500 dark:text-gray-400">{{ item.inventory_item_no || `Item #${item.item_id}` }} · Qty {{ item.quantity }}</span>
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </aside>
      </Transition>
    </template>
  </Modal>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import Modal from '../../../components/ui/dialogs/Modal.vue';
import api from '../../../lib/axios';

const props = defineProps({
  open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const previewMode = ref('html');
const selectionDrawerOpen = ref(false);
const itemsLoading = ref(false);
const itemsLoaded = ref(false);
const itemsError = ref('');
const pdfPreviewUrl = ref('');
const pdfPreviewLoading = ref(false);
const pdfPreviewError = ref('');
const pdfPreviewCurrent = ref(false);
const previewing = ref(false);
let pdfPreviewRequestId = 0;
let pdfPreviewRefreshTimer = null;
const eligibleByCategory = ref([]);
const selectedIds = ref([]);
const expandedCategories = ref([]);
const measureContainer = ref(null);
const previewPages = ref([{ type: 'items', items: [], details: [], startIndex: 0 }]);
let paginationRequest = 0;

const previewMetadata = ref({
  bid_description: 'public bidding for one (1) lot of Disposal of various unserviceable properties and equipment',
  corporation_requirement: 'SEC Registration Certificate for Corporation; or',
  proprietorship_requirement: 'DTI Registration Certificate for Sole Proprietorship;',
  business_permit_requirement: 'Valid Business Permit.',
  additional_requirements: [],
  school_name: 'Dian-ay National High School',
  school_address: 'Barangay Dian-ay, Escalante City',
  submission_deadline: new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10),
  submission_time: '10:00 AM',
  bid_opening_date: new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10),
  bid_opening_time: '10:00 AM',
  bid_submission_copies: '(3) (original copy (1) and 2 photocopies)',
  pullout_period: 'seven (7) calendar days',
  signatories: [
    { name: 'LILIBETH L. CASTRO', title: 'Teacher III', role: 'BAC Chairperson' },
    { name: 'RIZALINA P. MAXILOM', title: 'Principal I', role: 'Chairperson, Disposal Committee' },
    { name: 'LEE-RISA M. PRIETOS', title: 'BAC SECRETARIAT', role: '' },
    { name: 'MELISSA L. PRUDENTE', title: 'BAC MEMBER', role: '' },
    { name: 'ROBERTDAWN S. FUAMASING', title: 'BAC Member', role: '' },
    { name: 'CHARISSA MAE I. SAPA', title: 'BAC Member', role: '' },
  ],
  secretariat_name: 'Ms. LEE-RISA M. PRIETOS',
  secretariat_role: 'Administrative Assistant II',
  secretariat_role_detail: 'Head, BAC Secretariat',
  contact_numbers: '(034) 724-5720, (034) 454-0746',
  inspection_day: new Date().toISOString().slice(0, 10),
  inspection_time: '10:00 AM',
  auction_date: new Date(Date.now() + 86400000).toISOString().slice(0, 10),
  auction_time: '10:01 AM',
  form_acquisition_from: new Date(Date.now() - 86400000).toISOString().slice(0, 10),
  form_acquisition_to: new Date(Date.now() + 2 * 86400000).toISOString().slice(0, 10),
});

const detailKeys = [
  'submission',
  'requirements',
  'disqualification',
  'forms',
  'contacts',
  'inspection',
  'auction',
  'bidSubmission',
  'bidDeadline',
  'openingBids',
  'disposalCommittee',
  'awardingBids',
  'pulloutItems',
  'signatories',
];

const selectedPreviewItems = computed(() => eligibleByCategory.value
  .flatMap((group) => group.items)
  .filter((item) => selectedIds.value.includes(item.item_id)));

const pdfPreviewMessage = computed(() => {
  if (itemsLoading.value) return 'Loading disposal-eligible items…';
  if (selectedPreviewItems.value.length === 0) return 'Select at least one eligible item to generate the ITB PDF.';
  return 'PDF preview is not available.';
});

const categoryGroups = computed(() => eligibleByCategory.value
  .filter((group) => group.items.length > 0)
  .sort((a, b) => a.category_name.localeCompare(b.category_name)));

function addRequirement() {
  previewMetadata.value.additional_requirements.push('');
}

function removeRequirement(index) {
  previewMetadata.value.additional_requirements.splice(index, 1);
}

function isCategoryFullySelected(group) {
  return group.items.length > 0 && group.items.every((item) => selectedIds.value.includes(item.item_id));
}

function isCategoryPartiallySelected(group) {
  return group.items.some((item) => selectedIds.value.includes(item.item_id)) && !isCategoryFullySelected(group);
}

function toggleCategory(group) {
  if (isCategoryFullySelected(group)) {
    selectedIds.value = selectedIds.value.filter((id) => !group.items.some((item) => item.item_id === id));
  } else {
    const ids = new Set(selectedIds.value);
    group.items.forEach((item) => ids.add(item.item_id));
    selectedIds.value = [...ids];
  }
}

function toggleItem(itemId, checked) {
  if (checked) {
    if (!selectedIds.value.includes(itemId)) selectedIds.value = [...selectedIds.value, itemId];
  } else {
    selectedIds.value = selectedIds.value.filter((id) => id !== itemId);
  }
}

function toggleExpandedCategory(categoryId) {
  expandedCategories.value = expandedCategories.value.includes(categoryId)
    ? expandedCategories.value.filter((id) => id !== categoryId)
    : [...expandedCategories.value, categoryId];
}

function elementHeight(element, includeMargins = false) {
  if (!element) return 0;
  const rect = element.getBoundingClientRect();
  if (!includeMargins) return rect.height;
  const style = window.getComputedStyle(element);
  return rect.height + (Number.parseFloat(style.marginTop) || 0) + (Number.parseFloat(style.marginBottom) || 0);
}

async function refreshPreviewPages() {
  const request = ++paginationRequest;
  await nextTick();
  const container = measureContainer.value;
  const page = container?.querySelector('.itb-measure-page');
  if (!page || !container) return;

  const firstHeader = container.querySelector('[data-itb-header-first]');
  const continuationHeader = container.querySelector('[data-itb-header-continuation]');
  const intro = container.querySelector('[data-itb-intro]');
  const table = container.querySelector('[data-itb-table]');
  const tableHead = container.querySelector('[data-itb-table-head]');
  const footer = container.querySelector('[data-itb-footer]');
  const rowElements = [...container.querySelectorAll('[data-itb-row]')];
  const emptyRow = container.querySelector('[data-itb-empty-row]');
  const detailElements = new Map(detailKeys.map((key) => [key, container.querySelector(`[data-itb-detail="${key}"]`)]));
  const pageStyle = window.getComputedStyle(page);
  const contentHeight = page.clientHeight
    - (Number.parseFloat(pageStyle.paddingTop) || 0)
    - (Number.parseFloat(pageStyle.paddingBottom) || 0);
  const footerHeight = elementHeight(footer) + 8;
  const tableStyle = window.getComputedStyle(table);
  const tableHeaderHeight = elementHeight(tableHead)
    + (Number.parseFloat(tableStyle.marginTop) || 0)
    + (Number.parseFloat(tableStyle.marginBottom) || 0);
  const items = selectedPreviewItems.value;
  const itemHeights = rowElements.slice(0, items.length).map((row) => elementHeight(row));
  const detailHeights = new Map(detailKeys.map((key) => [
    key,
    elementHeight(detailElements.get(key), true),
  ]));
  const detailGap = 12;
  const detailSectionTop = 16;
  const pages = [];
  let itemIndex = 0;
  let detailIndex = 0;

  const addPage = (type, header, startIndex) => {
    const available = contentHeight - elementHeight(header, true) - footerHeight;
    const result = { type, items: [], details: [], startIndex, remaining: available };
    pages.push(result);
    return result;
  };
  const consumeDetails = (currentPage) => {
    while (detailIndex < detailKeys.length) {
      const key = detailKeys[detailIndex];
      const height = detailHeights.get(key) || 0;
      const spacing = currentPage.details.length === 0 ? detailSectionTop : detailGap;
      if (height + spacing > currentPage.remaining
        && (currentPage.items.length || currentPage.details.length || pages.length > 1)) {
        currentPage = addPage('details', continuationHeader, itemIndex);
      }
      currentPage.remaining -= (currentPage.details.length === 0 ? detailSectionTop : detailGap) + height;
      currentPage.details.push(key);
      detailIndex += 1;
    }
  };

  let currentPage = addPage('items', firstHeader, 0);
  currentPage.remaining -= (items.length > 0 ? elementHeight(intro, true) : 0) + tableHeaderHeight;
  if (items.length === 0) currentPage.remaining -= elementHeight(emptyRow);
  while (itemIndex < items.length) {
    const itemHeight = itemHeights[itemIndex] || 0;
    if (itemHeight > currentPage.remaining && (pages.length === 1 || itemIndex > currentPage.startIndex)) {
      currentPage = addPage('items', continuationHeader, itemIndex);
      currentPage.remaining -= tableHeaderHeight;
    }
    currentPage.items.push(items[itemIndex]);
    currentPage.remaining -= itemHeight;
    itemIndex += 1;
  }
  consumeDetails(currentPage);

  if (request === paginationRequest) {
    previewPages.value = pages;
  }
}

function clearPdfPreview() {
  pdfPreviewRequestId += 1;
  clearTimeout(pdfPreviewRefreshTimer);
  pdfPreviewRefreshTimer = null;
  if (pdfPreviewUrl.value) {
    URL.revokeObjectURL(pdfPreviewUrl.value);
    pdfPreviewUrl.value = '';
  }
  pdfPreviewLoading.value = false;
  previewing.value = false;
  pdfPreviewCurrent.value = false;
  pdfPreviewError.value = '';
}

async function loadEligibleItems() {
  if (itemsLoading.value || itemsLoaded.value) return;
  itemsLoading.value = true;
  itemsError.value = '';

  try {
    const collected = [];
    for (const workspace of ['disposed', 'ready_to_dispose']) {
      let page = 1;
      let lastPage = 1;
      do {
        const { data } = await api.get('/custodian/inventory', {
          params: { workspace, [`${workspace}_page`]: page },
        });
        const inventoryPage = data.inventoryPages?.[workspace];
        for (const group of inventoryPage?.data ?? []) {
          for (const source of group.sourceItems ?? []) {
            if (!['disposed', 'ready_to_dispose'].includes(source.status)) continue;
            collected.push({
              item_id: Number(source.item_id),
              item_name: source.item_name ?? '',
              inventory_item_no: source.inventory_item_no ?? '',
              quantity: Number(source.quantity ?? 0),
              unit_cost: Number(source.unit_cost ?? 0),
              floor_price: Number(source.quantity ?? 0) * Number(source.unit_cost ?? 0),
              unit: source.unit ?? 'Piece',
              category_id: source.category_id ?? source.category?.category_id ?? 0,
              category_name: source.category?.category_name ?? 'Uncategorized',
              status: source.status,
            });
          }
        }
        lastPage = Number(inventoryPage?.last_page ?? 1);
        page += 1;
      } while (page <= lastPage);
    }

    const map = new Map();
    for (const item of collected) {
      const key = String(item.category_id);
      if (!map.has(key)) {
        map.set(key, { category_id: item.category_id, category_name: item.category_name, items: [] });
      }
      map.get(key).items.push(item);
    }

    eligibleByCategory.value = [...map.values()].sort((a, b) => a.category_name.localeCompare(b.category_name));
    const ids = new Set(collected.map((item) => item.item_id));
    selectedIds.value = selectedIds.value.filter((id) => ids.has(id));
    expandedCategories.value = eligibleByCategory.value.map((group) => group.category_id);
    itemsLoaded.value = true;
    itemsError.value = '';
  } catch (error) {
    console.error('Failed to load ITB disposal-eligible items', error);
    itemsError.value = 'Could not load disposal-eligible items. Try again.';
  } finally {
    itemsLoading.value = false;
  }
}

function selectPdfPreview() {
  previewMode.value = 'pdf';
  pdfPreviewError.value = '';
  if (!selectedIds.value.length) return;
  if (!pdfPreviewCurrent.value && !pdfPreviewLoading.value) {
    generatePdfPreview();
  }
}

function schedulePdfPreviewRefresh() {
  pdfPreviewRequestId += 1;
  pdfPreviewCurrent.value = false;
  pdfPreviewLoading.value = false;
  previewing.value = false;
  pdfPreviewError.value = '';
  clearTimeout(pdfPreviewRefreshTimer);
  pdfPreviewRefreshTimer = null;

  if (!props.open || previewMode.value !== 'pdf') return;
  if (selectedIds.value.length === 0) {
    clearPdfPreview();
    return;
  }

  pdfPreviewRefreshTimer = setTimeout(() => {
    pdfPreviewRefreshTimer = null;
    generatePdfPreview();
  }, 500);
}

async function generatePdfPreview() {
  if (!props.open || !selectedIds.value.length || pdfPreviewLoading.value) return;
  clearTimeout(pdfPreviewRefreshTimer);
  pdfPreviewRefreshTimer = null;

  const selectedItemsById = new Map(eligibleByCategory.value.flatMap((group) => group.items).map((item) => [item.item_id, item]));
  const requestId = ++pdfPreviewRequestId;
  pdfPreviewLoading.value = true;
  previewing.value = true;
  pdfPreviewError.value = '';

  try {
    const { data } = await api.post('/custodian/inventory/itb-pdf', {
      selected_ids: [...selectedIds.value],
      floor_prices: selectedIds.value.map((itemId) => selectedItemsById.get(itemId)?.floor_price),
      metadata: { ...previewMetadata.value },
    }, {
      responseType: 'blob',
      skipToast: true,
    });

    if (requestId !== pdfPreviewRequestId) return;
    const previousUrl = pdfPreviewUrl.value;
    pdfPreviewUrl.value = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
    pdfPreviewCurrent.value = true;
    if (previousUrl) URL.revokeObjectURL(previousUrl);
  } catch (requestError) {
    if (requestId !== pdfPreviewRequestId) return;
    const responseData = requestError?.response?.data;
    if (responseData instanceof Blob) {
      try {
        const details = JSON.parse(await responseData.text());
        pdfPreviewError.value = details.message ?? Object.values(details.errors ?? {}).flat()[0] ?? 'Failed to generate ITB PDF preview.';
      } catch {
        pdfPreviewError.value = 'Failed to generate ITB PDF preview. Please try again.';
      }
    } else {
      pdfPreviewError.value = responseData?.message ?? 'Failed to generate ITB PDF preview. Please try again.';
    }
  } finally {
    if (requestId === pdfPreviewRequestId) {
      pdfPreviewLoading.value = false;
      previewing.value = false;
    }
  }
}

function downloadPdf() {
  if (!pdfPreviewUrl.value || !pdfPreviewCurrent.value) return;
  const link = document.createElement('a');
  link.href = pdfPreviewUrl.value;
  link.download = `ITB_${new Date().toISOString().slice(0, 10)}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();
}

function printDocument() {
  const printStyles = document.createElement('style');
  printStyles.textContent = `
    @page { size: A4 portrait; margin: 0; }
    @media print {
      body.itb-printing * { visibility: hidden !important; }
      body.itb-printing .itb-preview-pages,
      body.itb-printing .itb-preview-pages * { visibility: visible !important; }
      body.itb-printing [role="dialog"] { position: static !important; display: block !important; max-height: none !important; overflow: visible !important; border: 0 !important; box-shadow: none !important; }
      body.itb-printing .itb-modal-shell { position: static !important; display: block !important; height: auto !important; min-height: 0 !important; overflow: visible !important; border: 0 !important; background: #fff !important; }
      body.itb-printing .itb-preview-main { position: static !important; display: block !important; overflow: visible !important; padding: 0 !important; }
      body.itb-printing .itb-preview-pages { position: static !important; display: block !important; width: 210mm !important; margin: 0 !important; }
      body.itb-printing .itb-preview-page { width: 210mm !important; max-width: none !important; height: 297mm !important; min-height: 297mm !important; max-height: 297mm !important; margin: 0 !important; box-shadow: none !important; overflow: hidden !important; break-after: page; page-break-after: always; }
      body.itb-printing .itb-preview-page:last-child { break-after: auto; page-break-after: auto; }
      body.itb-printing .itb-preview-row { break-inside: avoid; page-break-inside: avoid; }
      body.itb-printing .itb-preview-controls { display: none !important; }
    }
  `;
  document.head.appendChild(printStyles);
  const clearPrintState = () => {
    document.body.classList.remove('itb-printing');
    printStyles.remove();
  };
  window.addEventListener('afterprint', clearPrintState, { once: true });
  document.body.classList.add('itb-printing');
  window.print();
}

function handleClose() {
  selectionDrawerOpen.value = false;
  previewMode.value = 'html';
  pdfPreviewError.value = '';
  clearPdfPreview();
  emit('close');
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      selectionDrawerOpen.value = false;
      loadEligibleItems();
      previewMode.value = 'html';
    } else {
      selectionDrawerOpen.value = false;
      previewMode.value = 'html';
      pdfPreviewError.value = '';
      clearPdfPreview();
    }
  },
  { immediate: true },
);

watch([selectedPreviewItems, previewMetadata], refreshPreviewPages, {
  deep: true,
  flush: 'post',
  immediate: true,
});

watch([selectedIds, selectedPreviewItems, previewMetadata], () => {
  schedulePdfPreviewRefresh();
}, {
  deep: true,
});

onUnmounted(() => {
  clearPdfPreview();
});
</script>

<style scoped>
.iirup-drawer-enter-active,
.iirup-drawer-leave-active {
  transition: transform 0.3s ease;
}

.iirup-drawer-enter-from,
.iirup-drawer-leave-to {
  transform: translateX(-100%);
}

.iirup-fade-enter-active,
.iirup-fade-leave-active {
  transition: opacity 0.25s ease;
}

.iirup-fade-enter-from,
.iirup-fade-leave-to {
  opacity: 0;
}
</style>
