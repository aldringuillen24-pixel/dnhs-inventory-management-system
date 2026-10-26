<template>
  <div class="space-y-6">
    <div
      ref="itbMeasureContainer"
      aria-hidden="true"
      style="position: absolute; left: -10000px; top: 0; visibility: hidden; pointer-events: none;"
    >
      <article class="itb-measure-page flex flex-col bg-white p-6 text-[14px] leading-relaxed text-black" style="width: 210mm; height: 297mm; box-sizing: border-box; font-family: Calibri, Arial, sans-serif;">
        <header data-itb-header-first class="mb-4 text-center">
          <img :src="'/images/header/Philippine%20Education%20Department%20Header.png'" alt="" class="mx-auto mb-3 block h-auto w-2/5 max-w-full object-contain" @load="refreshItbPreviewPages" />
          <hr class="my-3 border-0 border-t border-gray-400" />
          <p class="text-[16px] font-bold uppercase tracking-wide">INVITATION TO BID</p>
          <p class="text-[16px] font-semibold">Disposal of Unserviceable Properties Through Public Bidding</p>
        </header>
        <header data-itb-header-continuation class="mb-4 text-center">
          <img :src="'/images/header/Philippine%20Education%20Department%20Header.png'" alt="" class="mx-auto mb-3 block h-auto w-2/5 max-w-full object-contain" @load="refreshItbPreviewPages" />
          <hr class="my-3 border-0 border-t border-gray-400" />
        </header>
        <div data-itb-intro class="mt-3 flex items-start gap-3 text-justify">
          <span class="shrink-0">1.</span>
          <p>The Department of Education (DepEd) Schools Division of Escalante City, invites all interested parties to participate in the <strong><u>{{ itbPreviewMetadata.bid_description }}</u></strong> as indicated below:</p>
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
              <tr v-for="(item, index) in itbPreviewItems" :key="`measure-${item.item_id}`" data-itb-row class="itb-preview-row">
                <td class="border border-black px-2 py-1.5">{{ index + 1 }}</td>
                <td class="border border-black px-2 py-1.5">{{ item.item_name }}</td>
                <td class="border border-black px-2 py-1.5 text-right">{{ item.quantity }}</td>
                <td class="border border-black px-2 py-1.5 text-right"><input v-model.number="item.floor_price" type="number" min="0" step="0.01" :aria-label="`Floor price for ${item.item_name}`" class="w-full border-b border-dotted border-black bg-transparent text-right outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" /></td>
              </tr>
              <tr v-if="itbPreviewItems.length === 0" data-itb-empty-row>
                <td colspan="4" class="border border-black px-2 py-3 text-center text-black">Select disposal-eligible items in the drawer.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div data-itb-details class="mt-4 space-y-3 text-justify">
          <p data-itb-detail="submission"><strong>2.</strong> Submit your Bid form with your name, signature, company name, address, and contact numbers. Submit the duly accomplished quotation form on or before <input v-model="itbPreviewMetadata.submission_deadline" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-center text-[11px] outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" aria-label="Submission deadline" /> at {{ itbPreviewMetadata.school_name }}, {{ itbPreviewMetadata.school_address }}, and look for <span contenteditable="true" role="textbox" aria-label="Secretariat name" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.secretariat_name = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.secretariat_name }}</span>.</p>
          <div data-itb-detail="requirements" class="flex items-start gap-3">
            <strong class="shrink-0">3.</strong>
            <div>
              <p>Attach to your Bid either the following:</p>
              <ul class="ml-6 list-disc">
                <li><span contenteditable="true" role="textbox" aria-label="Corporation registration requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.corporation_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.corporation_requirement }}</span></li>
                <li><span contenteditable="true" role="textbox" aria-label="Sole proprietorship registration requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.proprietorship_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.proprietorship_requirement }}</span></li>
                <li><span contenteditable="true" role="textbox" aria-label="Business permit requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.business_permit_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.business_permit_requirement }}</span></li>
                <li v-for="(requirement, index) in itbPreviewMetadata.additional_requirements" :key="`itb-requirement-measure-${index}`">{{ requirement }}</li>
              </ul>
            </div>
          </div>
          <p data-itb-detail="disqualification"><strong>4.</strong> Unsigned Bid and Bid lower than the floor price shall automatically be disqualified.</p>
          <p data-itb-detail="forms"><strong>5.</strong> A complete set of Bid Form may be acquired by interested bidder from <input v-model="itbPreviewMetadata.form_acquisition_from" type="date" aria-label="Bid form availability start date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> to <input v-model="itbPreviewMetadata.form_acquisition_to" type="date" aria-label="Bid form availability end date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> at School Head office, {{ itbPreviewMetadata.school_name }}.</p>
          <div data-itb-detail="contacts" class="flex items-start gap-3">
            <strong class="shrink-0">6.</strong>
            <div>
              <p>For further information, please refer to:</p>
              <p class="mt-2"><strong>{{ itbPreviewMetadata.secretariat_name }}</strong><br />{{ itbPreviewMetadata.secretariat_role }}<br />{{ itbPreviewMetadata.secretariat_role_detail }}<br />{{ itbPreviewMetadata.contact_numbers }}</p>
            </div>
          </div>
          <p data-itb-detail="inspection"><strong>7.</strong> Inspection of Unserviceable Properties are available for inspection on <input v-model="itbPreviewMetadata.inspection_day" type="date" aria-label="Inspection date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> @ <input v-model="itbPreviewMetadata.inspection_time" type="text" aria-label="Inspection time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" />, {{ itbPreviewMetadata.school_name }}.</p>
          <div data-itb-detail="auction">
            <p><strong>8.</strong> Schedule of Public Auction:</p>
            <p class="pl-6">The public auction shall be conducted on an “as is, where is” basis on <input v-model="itbPreviewMetadata.auction_date" type="date" aria-label="Auction date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> at <input v-model="itbPreviewMetadata.auction_time" type="text" aria-label="Auction time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" />, {{ itbPreviewMetadata.school_name }}.</p>
          </div>
          <div data-itb-detail="bidSubmission">
            <p><strong>9.</strong> Submission of Bid:</p>
            <p class="pl-6">The Bid must be submitted in three <input v-model="itbPreviewMetadata.bid_submission_copies" type="text" size="1" aria-label="Bid submission copy details" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px] outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.bid_submission_copies.length, 1)}ch` }" /> using the prescribed Bid Form.</p>
          </div>
          <p data-itb-detail="bidDeadline" class="text-left"><strong>10.</strong> Deadline of submission of Bid is on <input v-model="itbPreviewMetadata.submission_deadline" type="date" aria-label="Bid submission deadline date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> <input v-model="itbPreviewMetadata.submission_time" type="text" aria-label="Bid submission deadline time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" />. Bid submitted beyond said deadline shall not be accepted.</p>
          <p data-itb-detail="openingBids" class="text-left"><strong>11.</strong> Opening of Bids at <input v-model="itbPreviewMetadata.bid_opening_date" type="date" aria-label="Bid opening date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> <input v-model="itbPreviewMetadata.bid_opening_time" type="text" aria-label="Bid opening time" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" /> in the presence of BAC and Division.</p>
          <p data-itb-detail="disposalCommittee"><strong>12.</strong> Disposal Committee.</p>
          <div data-itb-detail="awardingBids">
            <p><strong>13.</strong> Awarding of Bids:</p>
            <p class="pl-6">Award shall be made to the highest bidder.</p>
          </div>
          <div data-itb-detail="pulloutItems">
            <p><strong>14.</strong> Pull-out/Taking of Awarded Items:</p>
            <p class="pl-6">The winning bidder shall be given <input v-model="itbPreviewMetadata.pullout_period" type="text" size="1" aria-label="Pull-out period" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px] outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.pullout_period.length, 1)}ch` }" /> from receipt of payment to pull out from the Old Division Office as stated in the ITB. The unserviceable items awarded subject to the supervision of Division Supply Officer, Division Accountant, Representative from the Division Inventory and Disposal Committee, witnessing and approval by the Schools Division Superintendent and COA official during the actual disposal.</p>
          </div>
          <table data-itb-detail="signatories" style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 56px; font-size: 11pt; line-height: 1.35; text-align: left;">
            <tbody>
              <tr v-for="(row, rowIndex) in [[0, 1], [2, 3], [4, 5]]" :key="`measure-signatory-row-${rowIndex}`" :style="rowIndex === 0 ? 'height: 120px;' : rowIndex === 1 ? 'height: 90px;' : ''">
                <td v-for="index in row" :key="`measure-signatory-${index}`" style="width: 50%; padding: 0; vertical-align: top;">
                  <input v-model="itbPreviewMetadata.signatories[index].name" type="text" size="1" :aria-label="`Signatory ${index + 1} name`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 font-semibold outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.signatories[index].name.length * 0.72 + 2, 1)}em` }" />
                  <input v-model="itbPreviewMetadata.signatories[index].title" type="text" size="1" :aria-label="`Signatory ${index + 1} title`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${itbPreviewMetadata.signatories[index].title.length + 1}ch` }" />
                  <input v-if="itbPreviewMetadata.signatories[index].role" v-model="itbPreviewMetadata.signatories[index].role" type="text" size="1" :aria-label="`Signatory ${index + 1} role`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${itbPreviewMetadata.signatories[index].role.length + 1}ch` }" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer data-itb-footer class="mt-auto grid grid-cols-[45%_55%] items-center border-t border-gray-500 py-2 text-[11px] leading-tight">
          <div class="flex items-center gap-2">
            <img :src="'/images/footer/image.png'" alt="" class="block h-auto w-[150px] max-w-[calc(100%-50px)]" @load="refreshItbPreviewPages" />
            <img :src="'/images/footer/escalante_city_education_seal.svg'" alt="" class="block h-auto w-[42px] shrink-0" @load="refreshItbPreviewPages" />
          </div>
          <address class="not-italic">Escalante City, Negros Occidental, Philippines<br />Tel. Nos. - (034) 724-5720, (034) 454-0746<br />escalante.city001@deped.gov.ph<br />Depedescalantecity.weebly.com</address>
        </footer>
      </article>
    </div>
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Inventory Management</h1>
          <span
            v-if="metrics.total !== undefined"
            class="rounded-md bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"
          >
          </span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stock records grouped by item, category, unit, and condition.</p>
      </div>

      <div class="grid w-full grid-cols-2 gap-2.5 sm:flex sm:w-auto sm:items-center">
        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 shadow-sm transition-colors hover:bg-emerald-50 dark:border-emerald-500/30 dark:bg-gray-800 dark:text-emerald-300 dark:hover:bg-emerald-500/10 sm:w-auto"
          @click="openQrScanner"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7V5a1 1 0 0 1 1-1h2m10 0h2a1 1 0 0 1 1 1v2M4 17v2a1 1 0 0 0 1 1h2m10 0h2a1 1 0 0 0 1-1v-2M8 8h3v3H8zm5 0h3v3h-3zm-5 5h3v3H8zm6 0v1m2-1v3m-3-1h1" />
          </svg>
          Scan QR
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md bg-gradient-to-r from-emerald-600 to-teal-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition-all hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 sm:w-auto"
          @click="stockInOpen = true"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          Stock In
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-purple-200 bg-white px-3 py-2 text-xs font-semibold text-purple-800 shadow-sm transition-colors hover:bg-purple-50 dark:border-purple-500/30 dark:bg-gray-800 dark:text-purple-300 dark:hover:bg-purple-500/10 sm:w-auto"
          @click="printIIRUP"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M16 13H8M16 17H8M10 9H8" />
          </svg>
          Print IIRUP
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-sky-200 bg-white px-3 py-2 text-xs font-semibold text-sky-800 shadow-sm transition-colors hover:bg-sky-50 dark:border-sky-500/30 dark:bg-gray-800 dark:text-sky-300 dark:hover:bg-sky-500/10 sm:w-auto"
          @click="openRfqPreview"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M8 13h8M8 17h8" />
          </svg>
          Print RFQ
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-amber-200 bg-white px-3 py-2 text-xs font-semibold text-amber-800 shadow-sm transition-colors hover:bg-amber-50 dark:border-amber-500/30 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-500/10 sm:w-auto"
          @click="openItbCategoryDrawer"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M8 13h8M8 17h8" />
          </svg>
          Print ITB
        </button>

        <button
          type="button"
          class="inline-flex w-full items-center justify-center gap-2 whitespace-nowrap rounded-md border border-blue-200 bg-white px-3 py-2 text-xs font-semibold text-blue-800 shadow-sm transition-colors hover:bg-blue-50 dark:border-blue-500/30 dark:bg-gray-800 dark:text-blue-300 dark:hover:bg-blue-500/10 sm:w-auto"
          @click="openQrPrintModal"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H5a2 2 0 00-2 2v4h2a2 2 0 002 2zm0 0v2a2 2 0 002 2h6a2 2 0 002-2v-2" />
          </svg>
          Print QR Codes
        </button>
      </div>
    </div>

    <Modal
      :open="itbSelectionModalOpen"
      title="Print ITB"
      subtitle="Invitation to Bid for disposal of unserviceable properties"
      max-width="max-w-7xl"
      :fixed-body="true"
      @close="closeItbSelectionModal"
    >
      <div class="itb-modal-shell relative flex h-[70vh] min-h-0 flex-1 flex-col overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-2 dark:border-gray-700 dark:bg-gray-900">
          <h2 class="text-sm font-bold text-gray-900 dark:text-white">Document preview</h2>
          <div class="flex gap-1" aria-label="Preview format">
            <button type="button" class="rounded px-2.5 py-1 text-xs font-semibold" :class="itbPreviewMode === 'html' ? 'bg-white text-amber-700 shadow-sm dark:bg-gray-700 dark:text-amber-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'" :aria-pressed="itbPreviewMode === 'html'" @click="itbPreviewMode = 'html'">
              HTML Preview
            </button>
            <button type="button" class="rounded px-2.5 py-1 text-xs font-semibold" :class="itbPreviewMode === 'pdf' ? 'bg-white text-amber-700 shadow-sm dark:bg-gray-700 dark:text-amber-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'" :aria-pressed="itbPreviewMode === 'pdf'" :disabled="!itbSelectedIds.length" @click="selectItbPdfPreview">
              PDF Preview
            </button>
          </div>
        </div>
        <main class="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto p-4">
        <div v-if="itbPreviewMode === 'pdf'" class="relative h-[65vh] min-h-[24rem] overflow-hidden rounded bg-white">
          <iframe v-if="itbPdfPreviewUrl" :src="itbPdfPreviewUrl" class="h-full w-full border-0" title="ITB PDF preview" />
          <div v-else class="flex h-full items-center justify-center px-4 text-center text-sm text-gray-500" role="status">
            {{ itbPdfPreviewLoading ? 'Generating PDF preview…' : 'Select PDF Preview to generate the document.' }}
          </div>
          <div v-if="itbPdfPreviewLoading && itbPdfPreviewUrl" class="absolute right-2 top-2 rounded bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-900" role="status">
            Refreshing PDF preview…
          </div>
          <div v-if="itbPdfPreviewError" class="absolute bottom-2 left-2 right-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 shadow dark:border-rose-500/30 dark:bg-gray-900 dark:text-rose-300" role="alert">
            {{ itbPdfPreviewError }}
          </div>
        </div>
        <div v-else>
          <div class="itb-preview-status mx-auto mb-3 flex max-w-5xl items-center justify-between text-xs text-gray-600 dark:text-gray-300">
            <span>{{ itbPreviewItems.length }} item(s) selected for the invitation</span>
            <span v-if="itbConfirmedSelectedIds.length">Selection confirmed</span>
            <span v-else>Select items in the left panel to add them to this ITB.</span>
          </div>
          <div class="itb-preview-pages mx-auto flex flex-col items-center gap-4">
          <article
            v-for="(previewPage, pageIndex) in itbPreviewPages"
            :key="`itb-preview-page-${pageIndex}`"
            class="itb-preview-page flex flex-col bg-white p-6 text-[14px] leading-relaxed text-black shadow-sm"
            style="width: 210mm; max-width: 100%; height: 297mm; min-height: 297mm; box-sizing: border-box; font-family: Calibri, Arial, sans-serif;"
          >
            <header class="mb-4 text-center">
              <img
                :src="'/images/header/Philippine%20Education%20Department%20Header.png'"
                alt="Department of Education, Schools Division of Escalante City"
                class="mx-auto mb-3 block h-auto w-2/5 max-w-full object-contain"
              />
              <hr class="my-3 border-0 border-t border-gray-400" />
              <template v-if="pageIndex === 0">
                <p class="text-[16px] font-bold uppercase tracking-wide">INVITATION TO BID</p>
                <p class="text-[16px] font-semibold">Disposal of Unserviceable Properties Through Public Bidding</p>
              </template>
            </header>
            <div v-if="pageIndex === 0" class="mt-3 flex items-start gap-3 text-justify">
              <span class="shrink-0">1.</span>
              <p>
                The Department of Education (DepEd) Schools Division of Escalante City, invites all interested parties to participate in the <strong><span contenteditable="true" role="textbox" aria-label="ITB bidding description" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.bid_description = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.bid_description }}</span></strong> as indicated below:
              </p>
            </div>
            <div v-if="previewPage.type === 'items'" class="mt-3 overflow-x-auto">
              <table class="w-full border-collapse border border-black text-left text-[11px]">
                <thead>
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
                    <td class="border border-black px-2 py-1.5 text-right"><input v-model.number="item.floor_price" type="number" min="0" step="0.01" :aria-label="`Floor price for ${item.item_name}`" class="w-full border-b border-dotted border-black bg-transparent text-right outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" /></td>
                  </tr>
                  <tr v-if="previewPage.type === 'items' && itbPreviewItems.length === 0">
                    <td colspan="4" class="border border-black px-2 py-3 text-center text-black">Select disposal-eligible items in the drawer.</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <section v-if="previewPage.details.length" class="mt-4 space-y-3 text-justify">
              <p v-if="previewPage.details.includes('submission')" data-itb-detail="submission">
                <strong>2.</strong> Submit your Bid form with your name, signature, company name, address, and contact numbers. Submit the duly accomplished quotation form on or before
                <input v-model="itbPreviewMetadata.submission_deadline" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-center text-[11px] outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" aria-label="Submission deadline" />
                at {{ itbPreviewMetadata.school_name }}, {{ itbPreviewMetadata.school_address }}, and look for <span contenteditable="true" role="textbox" aria-label="Secretariat name" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.secretariat_name = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.secretariat_name }}</span>.
              </p>
              <div v-if="previewPage.details.includes('requirements')" data-itb-detail="requirements" class="flex items-start gap-3">
                <strong class="shrink-0">3.</strong>
                <div>
                  <p>Attach to your Bid either the following:</p>
                  <ul class="ml-6 list-disc">
                    <li><span contenteditable="true" role="textbox" aria-label="Corporation registration requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.corporation_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.corporation_requirement }}</span></li>
                    <li><span contenteditable="true" role="textbox" aria-label="Sole proprietorship registration requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.proprietorship_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.proprietorship_requirement }}</span></li>
                    <li><span contenteditable="true" role="textbox" aria-label="Business permit requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.business_permit_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.business_permit_requirement }}</span></li>
                    <li v-for="(requirement, index) in itbPreviewMetadata.additional_requirements" :key="`itb-requirement-modal-${index}`"><input v-model="itbPreviewMetadata.additional_requirements[index]" type="text" :aria-label="`Additional requirement ${index + 1}`" class="inline-block w-[calc(100%-2rem)] border-0 border-b border-dotted border-black bg-transparent p-0 align-middle outline-none focus:border-solid focus:border-amber-600" /><button type="button" class="float-right inline-flex h-5 w-5 items-center justify-center rounded-full border-0 text-gray-500 hover:bg-rose-50 hover:text-rose-600 focus:border-0 focus:outline-none focus:ring-0" :aria-label="`Remove additional requirement ${index + 1}`" title="Remove requirement" @click="removeItbRequirement(index)"><svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" /></svg></button></li>
                  </ul>
                  <button type="button" class="mt-1 inline-flex items-center gap-1 rounded border-0 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50 focus:border-0 focus:outline-none focus:ring-0" aria-label="Add requirement" title="Add requirement" @click="addItbRequirement">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z" /></svg>
                    Add requirement
                  </button>
                </div>
              </div>
              <p v-if="previewPage.details.includes('disqualification')" data-itb-detail="disqualification"><strong>4.</strong> Unsigned Bid and Bid lower than the floor price shall automatically be disqualified.</p>
              <p v-if="previewPage.details.includes('forms')" data-itb-detail="forms"><strong>5.</strong> A complete set of Bid Form may be acquired by interested bidder from <input v-model="itbPreviewMetadata.form_acquisition_from" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid form availability start date" /> to <input v-model="itbPreviewMetadata.form_acquisition_to" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid form availability end date" /> at School Head office, {{ itbPreviewMetadata.school_name }}.</p>
              <div v-if="previewPage.details.includes('contacts')" data-itb-detail="contacts" class="flex items-start gap-3">
                <strong class="shrink-0">6.</strong>
                <div class="min-w-0 flex-1">
                  <p>For further information, please refer to:</p>
                  <p class="mt-2">
                    <input v-model="itbPreviewMetadata.secretariat_name" type="text" size="1" aria-label="Secretariat name" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 font-semibold outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.secretariat_name.length, 1)}ch` }" />
                    <input v-model="itbPreviewMetadata.secretariat_role" type="text" size="1" aria-label="Secretariat role" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.secretariat_role.length, 1)}ch` }" />
                    <input v-model="itbPreviewMetadata.secretariat_role_detail" type="text" size="1" aria-label="Secretariat role detail" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.secretariat_role_detail.length, 1)}ch` }" />
                    <input v-model="itbPreviewMetadata.contact_numbers" type="text" size="1" aria-label="Secretariat contact numbers" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.contact_numbers.length, 1)}ch` }" />
                  </p>
                </div>
              </div>
              <p v-if="previewPage.details.includes('inspection')" data-itb-detail="inspection"><strong>7.</strong> Inspection of Unserviceable Properties are available for inspection on <input v-model="itbPreviewMetadata.inspection_day" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Inspection date" /> @ <input v-model="itbPreviewMetadata.inspection_time" type="text" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Inspection time" />, {{ itbPreviewMetadata.school_name }}.</p>
              <div v-if="previewPage.details.includes('auction')" data-itb-detail="auction">
                <p><strong>8.</strong> Schedule of Public Auction:</p>
                <p class="pl-6">The public auction shall be conducted on an “as is, where is” basis on <input v-model="itbPreviewMetadata.auction_date" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Auction date" /> at <input v-model="itbPreviewMetadata.auction_time" type="text" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Auction time" />, {{ itbPreviewMetadata.school_name }}.</p>
              </div>
              <div v-if="previewPage.details.includes('bidSubmission')" data-itb-detail="bidSubmission">
                <p><strong>9.</strong> Submission of Bid:</p>
                <p class="pl-6">The Bid must be submitted in three <input v-model="itbPreviewMetadata.bid_submission_copies" type="text" size="1" aria-label="Bid submission copy details" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px] outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.bid_submission_copies.length, 1)}ch` }" /> using the prescribed Bid Form.</p>
              </div>
              <p v-if="previewPage.details.includes('bidDeadline')" data-itb-detail="bidDeadline" class="text-left">
                <strong>10.</strong> Deadline of submission of Bid is on
                <input v-model="itbPreviewMetadata.submission_deadline" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid submission deadline date" />
                <input v-model="itbPreviewMetadata.submission_time" type="text" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid submission deadline time" />. Bid submitted beyond said deadline shall not be accepted.
              </p>
              <p v-if="previewPage.details.includes('openingBids')" data-itb-detail="openingBids" class="text-left">
                <strong>11.</strong> Opening of Bids at
                <input v-model="itbPreviewMetadata.bid_opening_date" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid opening date" />
                <input v-model="itbPreviewMetadata.bid_opening_time" type="text" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid opening time" /> in the presence of BAC and Division.
              </p>
              <p v-if="previewPage.details.includes('disposalCommittee')" data-itb-detail="disposalCommittee"><strong>12.</strong> Disposal Committee.</p>
              <div v-if="previewPage.details.includes('awardingBids')" data-itb-detail="awardingBids">
                <p><strong>13.</strong> Awarding of Bids:</p>
                <p class="pl-6">Award shall be made to the highest bidder.</p>
              </div>
              <div v-if="previewPage.details.includes('pulloutItems')" data-itb-detail="pulloutItems">
                <p><strong>14.</strong> Pull-out/Taking of Awarded Items:</p>
                <p class="pl-6">The winning bidder shall be given <input v-model="itbPreviewMetadata.pullout_period" type="text" size="1" aria-label="Pull-out period" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px] outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.pullout_period.length, 1)}ch` }" /> from receipt of payment to pull out from the Old Division Office as stated in the ITB. The unserviceable items awarded subject to the supervision of Division Supply Officer, Division Accountant, Representative from the Division Inventory and Disposal Committee, witnessing and approval by the Schools Division Superintendent and COA official during the actual disposal.</p>
              </div>
              <table v-if="previewPage.details.includes('signatories')" data-itb-detail="signatories" style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 56px; font-size: 11pt; line-height: 1.35; text-align: left;">
                <tbody>
                  <tr v-for="(row, rowIndex) in [[0, 1], [2, 3], [4, 5]]" :key="`preview-signatory-row-${rowIndex}`" :style="rowIndex === 0 ? 'height: 120px;' : rowIndex === 1 ? 'height: 90px;' : ''">
                    <td v-for="index in row" :key="`preview-signatory-${index}`" style="width: 50%; padding: 0; vertical-align: top;">
                      <input v-model="itbPreviewMetadata.signatories[index].name" type="text" size="1" :aria-label="`Signatory ${index + 1} name`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 font-semibold outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.signatories[index].name.length * 0.72 + 2, 1)}em` }" />
                      <input v-model="itbPreviewMetadata.signatories[index].title" type="text" size="1" :aria-label="`Signatory ${index + 1} title`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${itbPreviewMetadata.signatories[index].title.length + 1}ch` }" />
                      <input v-if="itbPreviewMetadata.signatories[index].role" v-model="itbPreviewMetadata.signatories[index].role" type="text" size="1" :aria-label="`Signatory ${index + 1} role`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${itbPreviewMetadata.signatories[index].role.length + 1}ch` }" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </section>
            <footer class="mt-auto grid grid-cols-[45%_55%] items-center border-t border-gray-500 py-2 text-[11px] leading-tight">
              <div class="flex items-center gap-2">
                <img
                  :src="'/images/footer/image.png'"
                  alt="Department of Education and Bagong Pilipinas logos"
                  class="block h-auto w-[150px] max-w-[calc(100%-50px)]"
                />
                <img
                  :src="'/images/footer/escalante_city_education_seal.svg'"
                  alt="Escalante City Division Department of Education seal"
                  class="block h-auto w-[42px] shrink-0"
                />
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
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            @click="closeItbSelectionModal"
          >
            Cancel
          </button>
          <button
            v-if="itbPreviewMode === 'pdf'"
            type="button"
            class="rounded-md border border-amber-200 bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-500/30"
            :disabled="!itbPdfPreviewUrl || !itbPdfPreviewCurrent || itbPdfPreviewLoading"
            @click="downloadItbPdf"
          >
            Download PDF
          </button>
          <button
            v-if="itbPreviewMode === 'html'"
            type="button"
            class="rounded-md border border-amber-200 bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-500/30"
            @click="printItbDocument"
          >
            Print ITB
          </button>
        </div>
      </template>
      <template #overlay>
        <button
          type="button"
          class="absolute left-0 top-36 z-[100001] flex items-center rounded-r-md border border-l-0 border-amber-200 bg-white px-3 py-3 text-xs font-semibold text-amber-700 shadow-lg transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-500 dark:border-amber-500/30 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-500/10"
          :aria-expanded="itbSelectionDrawerOpen"
          :aria-label="itbSelectionDrawerOpen ? 'Close ITB category drawer' : 'Open ITB category drawer'"
          aria-controls="itb-category-selection-drawer"
          @click="itbSelectionDrawerOpen = !itbSelectionDrawerOpen"
        >
          <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': itbSelectionDrawerOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
          </svg>
        </button>
        <Transition name="iirup-fade">
          <div
            v-if="itbSelectionDrawerOpen"
            class="absolute inset-0 z-[100000] rounded-md bg-gray-900/40"
            aria-hidden="true"
            @click="itbSelectionDrawerOpen = false"
          />
        </Transition>
        <Transition name="iirup-drawer">
          <aside
            v-if="itbSelectionDrawerOpen"
            id="itb-category-selection-drawer"
            class="absolute inset-y-0 left-0 z-[100001] flex w-full max-w-sm flex-col rounded-l-md border-r border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950"
            aria-label="Select disposal-eligible items for ITB"
          >
            <header class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Select disposal-eligible items</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ itbSelectedIds.length }} item(s) selected</p>
              </div>
              <button type="button" class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300" aria-label="Close ITB category drawer" @click="itbSelectionDrawerOpen = false">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            </header>
            <div v-if="itbItemsLoading" class="flex min-h-0 flex-1 items-center justify-center p-6 text-center text-sm text-gray-500 dark:text-gray-400" role="status">
              Loading disposal-eligible items…
            </div>
            <div v-else-if="itbItemsError" class="m-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300" role="alert">
              <p>{{ itbItemsError }}</p>
              <button type="button" class="mt-3 rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700" @click="loadItbEligibleItems">Retry</button>
            </div>
            <div v-else-if="itbItemsLoaded && itbCategoryGroups.length === 0" class="m-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-center text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
              No categories with disposal-eligible items were found.
            </div>
            <div v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
              <div v-for="group in itbCategoryGroups" :key="group.category_id" class="rounded-md border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 px-3 py-2">
                  <input
                    type="checkbox"
                    :checked="isItbCategoryFullySelected(group)"
                    :indeterminate="isItbCategoryPartiallySelected(group)"
                    :aria-label="`Select all eligible items in ${group.category_name}`"
                    class="h-3.5 w-3.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-800"
                    @change="toggleItbCategorySelection(group)"
                  />
                  <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center justify-between gap-2 text-left text-xs font-semibold text-gray-800 dark:text-gray-200"
                    :aria-expanded="itbExpandedCategories.includes(group.category_id)"
                    @click="toggleItbCategory(group.category_id)"
                  >
                    <span class="truncate">{{ group.category_name }}</span>
                    <span class="shrink-0 text-[10px] text-gray-500 dark:text-gray-400">({{ group.items.length }})</span>
                  </button>
                </div>
                <ul v-if="itbExpandedCategories.includes(group.category_id)" class="border-t border-gray-100 dark:border-gray-700">
                  <li v-for="item in group.items" :key="item.item_id" class="flex items-start gap-2 px-3 py-2">
                    <input
                      type="checkbox"
                      :checked="itbSelectedIds.includes(item.item_id)"
                      :aria-label="`Select ${item.item_name}`"
                      class="mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-800"
                      @change="toggleItbItem(item.item_id, $event.target.checked)"
                    />
                    <span class="min-w-0 flex-1">
                      <span class="block text-xs font-medium text-gray-800 dark:text-gray-100">{{ item.item_name }}</span>
                      <span class="mt-0.5 block text-[11px] text-gray-500 dark:text-gray-400">{{ item.inventory_item_no || `Item #${item.item_id}` }} · {{ item.quantity }} {{ item.unit || 'Piece' }}</span>
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </aside>
        </Transition>
        <div v-if="false">
        <button
          type="button"
          class="absolute left-0 top-26 z-[100001] flex items-center rounded-r-md border border-l-0 border-amber-200 bg-white px-3 py-3 text-xs font-semibold text-amber-700 shadow-lg transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-amber-500 dark:border-amber-500/30 dark:bg-gray-800 dark:text-amber-300 dark:hover:bg-amber-500/10"
          :aria-expanded="itbSelectionDrawerOpen"
          :aria-label="itbSelectionDrawerOpen ? 'Close ITB category drawer' : 'Open ITB category drawer'"
          aria-controls="itb-category-selection-drawer"
          @click="itbSelectionDrawerOpen = !itbSelectionDrawerOpen"
        >
          <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': itbSelectionDrawerOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
          </svg>
        </button>
        <Transition name="iirup-fade">
          <div
            v-if="itbSelectionDrawerOpen"
            class="absolute inset-0 z-[100000] rounded-md bg-gray-900/40"
            aria-hidden="true"
            @click="itbSelectionDrawerOpen = false"
          />
        </Transition>
        <Transition name="iirup-drawer">
          <aside
            v-if="itbSelectionDrawerOpen"
            id="itb-category-selection-drawer"
            class="absolute inset-y-0 left-0 z-[100001] flex w-full max-w-sm flex-col rounded-l-md border-r border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-950"
            aria-label="Select disposal-eligible items for ITB"
          >
            <header class="flex shrink-0 items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
              <div>
                <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">
                  {{ itbSelectionStep === 'review' ? 'Review ITB items' : 'Select disposal-eligible items' }}
                </h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ itbSelectedIds.length }} item(s) selected</p>
              </div>
              <button type="button" class="rounded-md p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300" aria-label="Close ITB category drawer" @click="itbSelectionDrawerOpen = false">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            </header>
            <div v-if="itbItemsLoading" class="flex min-h-0 flex-1 items-center justify-center p-6 text-center text-sm text-gray-500 dark:text-gray-400" role="status">
              Loading disposal-eligible items…
            </div>
            <div v-else-if="itbItemsError" class="m-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300" role="alert">
              <p>{{ itbItemsError }}</p>
              <button type="button" class="mt-3 rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700" @click="loadItbEligibleItems">
                Retry
              </button>
            </div>
            <template v-else-if="itbSelectionStep === 'review'">
              <div class="min-h-0 flex-1 overflow-y-auto p-3">
                <p class="mb-3 text-xs text-gray-600 dark:text-gray-400">Confirm these are the items to include in the ITB.</p>
                <ul class="space-y-2">
                  <li v-for="item in itbSelectedItems" :key="item.item_id" class="rounded-md border border-gray-200 px-3 py-2 dark:border-gray-700">
                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-100">{{ item.item_name }}</p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ item.category_name }} · {{ item.inventory_item_no || `Item #${item.item_id}` }} · Qty {{ item.quantity }}</p>
                  </li>
                </ul>
              </div>
              <footer class="flex shrink-0 justify-between gap-2 border-t border-gray-200 p-3 dark:border-gray-800">
                <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" @click="itbSelectionStep = 'select'">
                  Back to categories
                </button>
                <button type="button" class="rounded-md bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700" @click="confirmItbItems">
                  Confirm {{ itbSelectedItems.length }} item(s)
                </button>
              </footer>
            </template>
            <template v-else-if="itbSelectionStep === 'confirmed' && itbConfirmedSelectedIds.length > 0">
              <div class="flex min-h-0 flex-1 flex-col items-center justify-center p-6 text-center">
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300" role="status">{{ itbConfirmedSelectedIds.length }} item(s) confirmed for the ITB.</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">You can update your selection before the preview step.</p>
                <div class="mt-4 flex gap-2">
                  <button type="button" class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5" @click="editItbItems">
                    Edit selection
                  </button>
                  <button type="button" class="rounded-md bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700" @click="openItbPreview">
                    Preview ITB
                  </button>
                </div>
              </div>
            </template>
            <div v-else-if="itbItemsLoaded && itbCategoryGroups.length === 0" class="m-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-center text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
              No categories are available and no disposal-eligible items were found.
            </div>
            <div v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto p-3">
              <div v-for="group in itbCategoryGroups" :key="group.category_id" class="rounded-md border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 px-3 py-2">
                  <input
                    type="checkbox"
                    :checked="isItbCategoryFullySelected(group)"
                    :indeterminate="isItbCategoryPartiallySelected(group)"
                    :disabled="group.items.length === 0"
                    :aria-label="`Select all eligible items in ${group.category_name}`"
                    class="h-3.5 w-3.5 rounded-md border-gray-300 text-amber-600 focus:ring-amber-500 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800"
                    @change="toggleItbCategorySelection(group)"
                  />
                  <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center justify-between gap-2 text-left text-xs font-semibold text-gray-800 dark:text-gray-200"
                    :aria-expanded="itbExpandedCategories.includes(group.category_id)"
                    @click="toggleItbCategory(group.category_id)"
                  >
                    <span class="truncate">{{ group.category_name }}</span>
                    <span class="shrink-0 text-[10px] font-normal text-gray-500 dark:text-gray-400">{{ group.items.length }} eligible</span>
                  </button>
                </div>
                <div v-if="itbExpandedCategories.includes(group.category_id)" class="border-t border-gray-100 dark:border-gray-700">
                  <p v-if="group.items.length === 0" class="px-3 py-3 text-center text-xs text-gray-500 dark:text-gray-400">No disposal-eligible items in this category.</p>
                  <ul v-else class="divide-y divide-gray-100 dark:divide-white/5">
                    <li v-for="item in group.items" :key="item.item_id" class="flex items-start gap-2 px-3 py-2">
                      <input
                        type="checkbox"
                        :checked="itbSelectedIds.includes(item.item_id)"
                        :aria-label="`Select ${item.item_name}`"
                        class="mt-0.5 h-3.5 w-3.5 rounded-md border-gray-300 text-amber-600 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-800"
                        @change="toggleItbItem(item.item_id, $event.target.checked)"
                      />
                      <span class="min-w-0 flex-1">
                        <span class="block text-xs font-medium text-gray-800 dark:text-gray-100">{{ item.item_name }}</span>
                        <span class="mt-0.5 block text-[11px] text-gray-500 dark:text-gray-400">{{ item.inventory_item_no || `Item #${item.item_id}` }} · Qty {{ item.quantity }}</span>
                      </span>
                    </li>
                  </ul>
                </div>
              </div>
              <p v-if="itbCategoryGroups.length > 0 && itbEligibleItemCount === 0" class="rounded-md border border-gray-200 bg-gray-50 p-3 text-center text-xs text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                No disposal-eligible items were found in any category.
              </p>
            </div>
            <footer v-if="!itbItemsLoading && !itbItemsError && itbSelectionStep === 'select' && itbConfirmedSelectedIds.length === 0" class="flex shrink-0 justify-end border-t border-gray-200 p-3 dark:border-gray-800">
              <button type="button" class="rounded-md bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="itbSelectedIds.length === 0" @click="itbSelectionStep = 'review'">
                Review {{ itbSelectedIds.length }} selected
              </button>
            </footer>
          </aside>
        </Transition>
        </div>
      </template>
    </Modal>

    <!-- Error State -->
    <div
      v-if="error && !isIncidentWorkspace"
      class="flex items-center justify-between rounded-md border border-rose-200 bg-rose-50 p-5 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400"
    >
      <div class="flex items-center gap-3">
        <svg class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>{{ error }}</span>
      </div>
      <button
        type="button"
        class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700"
        @click="load"
      >
        Retry
      </button>
    </div>

    <template v-else>
      <!-- Main Inventory Panel -->
      <div class="custodian-panel relative flex max-h-[calc(100vh-7rem)] min-h-[32rem] flex-col overflow-hidden rounded-md border border-gray-200/80 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <!-- Interactive Workspace Tabs -->
        <div class="relative shrink-0 border-b border-gray-200/80 bg-gray-50/50 p-2.5 dark:border-gray-800 dark:bg-gray-900/50 sm:hidden">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-200 bg-white px-2.5 py-2 text-left text-xs font-semibold text-gray-800 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 min-[480px]:gap-3 min-[480px]:px-3 min-[480px]:py-2.5 min-[480px]:text-sm"
            aria-label="Choose inventory status"
            :aria-expanded="workspaceMenuOpen"
            aria-controls="inventory-workspace-options"
            @click="workspaceMenuOpen = !workspaceMenuOpen"
            @keydown.esc="workspaceMenuOpen = false"
          >
            <span class="flex min-w-0 items-center gap-2 min-[480px]:gap-2.5">
              <svg class="h-3.5 w-3.5 shrink-0 text-gray-500 dark:text-gray-400 min-[480px]:h-4 min-[480px]:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
              <span class="truncate">{{ activeWorkspaceTab.label }}</span>
              <span
                :key="`trigger:${activeWorkspaceTab.key}:${activeWorkspaceTab.count}`"
                class="inline-block shrink-0 rounded-md px-1.5 py-0.5 text-[9px] font-bold min-[480px]:px-2 min-[480px]:text-[10px]"
                :class="[tabBadgeClass(activeWorkspaceTab.key, true), animationsLive && 'tab-badge-pop']"
              >
                {{ format(activeWorkspaceTab.count) }}
              </span>
            </span>
            <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform min-[480px]:h-4 min-[480px]:w-4" :class="{ 'rotate-180': workspaceMenuOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
            </svg>
          </button>

          <div
            v-if="workspaceMenuOpen"
            id="inventory-workspace-options"
            class="absolute inset-x-2.5 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-md border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-800"
          >
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-medium transition-colors"
              :class="workspace === tab.key
                ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-300'
                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5'"
              :aria-pressed="workspace === tab.key"
              @click="selectWorkspace(tab.key)"
            >
              <span>{{ tab.label }}</span>
              <span
                :key="`${tab.key}:${tab.count}`"
                class="inline-block rounded-md px-2 py-0.5 text-[10px] font-bold"
                :class="[tabBadgeClass(tab.key, workspace === tab.key), animationsLive && 'tab-badge-pop']"
              >
                {{ format(tab.count) }}
              </span>
            </button>
            <button
              type="button"
              class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2.5 text-left text-sm font-medium transition-colors"
              :class="logsOpen
                ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-300'
                : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5'"
              :aria-expanded="logsOpen"
              aria-controls="item-logs-drawer"
              @click="workspaceMenuOpen = false; logsOpen = true"
            >
              <span>Item logs</span>
              <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </button>
          </div>
        </div>

        <div ref="tabStripRef" class="relative hidden shrink-0 flex-nowrap gap-1.5 overflow-x-auto border-b border-gray-200/80 bg-gray-50/50 p-2 dark:border-gray-800 dark:bg-gray-900/50 sm:flex">
          <span
            aria-hidden="true"
            class="pointer-events-none absolute inset-y-2 rounded-md bg-white shadow-sm ring-1 ring-emerald-500/20 motion-safe:transition-all motion-safe:duration-200 dark:bg-gray-800 dark:ring-emerald-500/30"
            :style="pillStyle"
          />
          <button
            v-for="tab in tabs"
            :key="tab.key"
            :data-workspace-tab="tab.key"
            type="button"
            class="group relative inline-flex min-h-[2rem] shrink-0 items-center gap-2 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
            :class="
              workspace === tab.key
                ? 'text-emerald-900 dark:text-emerald-300'
                : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'
            "
            :aria-pressed="workspace === tab.key"
            @click="switchWorkspace(tab.key)"
          >
            <span>{{ tab.label }}</span>
            <span
              :key="`${tab.key}:${tab.count}`"
              class="inline-block rounded-md px-1.5 py-0.5 text-[11px] font-bold tabular-nums transition-colors"
              :class="[tabBadgeClass(tab.key, workspace === tab.key), animationsLive && 'tab-badge-pop']"
            >
              {{ format(tab.count) }}
            </span>
          </button>
          <button
            type="button"
            class="ml-auto inline-flex min-h-[2rem] shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md px-2.5 py-1.5 text-left text-xs font-semibold leading-none transition-all"
            :class="logsOpen
              ? 'bg-white text-emerald-900 shadow-sm ring-1 ring-emerald-500/20 dark:bg-gray-800 dark:text-emerald-300 dark:ring-emerald-500/30'
              : 'text-gray-600 hover:bg-white/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200'"
            :aria-expanded="logsOpen"
            aria-controls="item-logs-drawer"
            @click="logsOpen = !logsOpen"
          >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Item logs</span>
          </button>
        </div>

        <!-- Toolbar: Search + Category + Stats + Bulk Actions -->
        <div v-if="!isIncidentWorkspace" class="flex shrink-0 flex-col gap-2.5 border-b border-gray-100 p-2.5 dark:border-white/5 min-[480px]:gap-3 min-[480px]:p-4 sm:flex-row sm:items-center">
          <!-- Search Input -->
          <div class="relative w-full min-w-0 sm:max-w-[17rem] sm:flex-shrink-0">
            <svg class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
            </svg>
            <input
              v-model="search"
              type="search"
              placeholder="Search item, category, assignee, ICS no…"
              aria-label="Search inventory"
              class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 pl-7 pr-7 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100 dark:focus:border-emerald-400 dark:focus:bg-gray-800 [&::-webkit-search-cancel-button]:hidden"
            />
            <button
              v-if="search"
              type="button"
              class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
              @click="search = ''"
            >
              <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Category Select -->
          <div class="w-full min-w-0 sm:max-w-[18rem] sm:flex-shrink-0">
            <select
              v-model="categoryFilter"
              aria-label="Filter inventory by category"
              class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100 dark:focus:border-emerald-400"
            >
              <option value="">All Categories ({{ categories.length }})</option>
              <option v-for="category in categories" :key="category.category_id" :value="category.category_id">
                {{ category.category_name }}
              </option>
            </select>
          </div>

          <!-- Export PDF -->
          <button
            type="button"
            class="shrink-0 inline-flex w-full items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-[12px] font-semibold text-gray-700 transition-colors hover:bg-gray-50 hover:border-gray-300 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-200 dark:hover:bg-gray-700/60 sm:w-auto"
            @click="openExportPdfModal"
          >
            <svg class="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M16 13H8M16 17H8M10 9H8" />
            </svg>
            Export PDF
          </button>

          <!-- Result Counter -->
          <div class="text-[11px] font-medium text-gray-500 dark:text-gray-400 min-[480px]:text-xs sm:ml-auto">
            Showing <strong class="text-gray-800 dark:text-white">{{ format(filteredRows.length) }}</strong> of {{ format(paginator.total) }} groups
          </div>
        </div>

        <!-- Bulk Selection Alert Banner -->
        <div
          v-if="selection.length"
          class="flex shrink-0 items-center justify-between gap-3 bg-amber-50 px-4 py-2.5 text-xs text-amber-900 border-b border-amber-200/60 dark:bg-amber-950/30 dark:text-amber-200 dark:border-amber-800/40"
        >
          <div class="flex items-center gap-2">
            <span class="flex h-5 w-5 items-center justify-center rounded-md bg-amber-200 text-amber-900 text-[10px] font-bold dark:bg-amber-800 dark:text-amber-100">
              {{ selection.length }}
            </span>
            <span>item group(s) selected</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="font-semibold text-amber-800 underline hover:text-amber-950 dark:text-amber-300"
              @click="selection = []"
            >
              Deselect All
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3 py-1.5 font-bold text-white shadow-sm hover:bg-rose-700"
              @click="confirmBulk = true"
            >
              <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Delete Selected
            </button>
          </div>
        </div>

        <Transition v-if="!isIncidentWorkspace" name="workspace" mode="out-in">
          <div :key="workspace" class="flex min-h-0 min-w-0 flex-1 flex-col">
        <div v-if="loading" class="min-h-0 min-w-0 flex-1 shrink space-y-2.5 overflow-y-auto p-3 xl:hidden" aria-hidden="true">
          <div v-for="row in 4" :key="row" class="space-y-2.5 rounded-md border border-gray-200 p-3 dark:border-gray-800">
            <div class="h-3.5 w-2/3 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="h-3 w-1/2 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
            <div class="h-10 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          </div>
        </div>
        <InventoryTableSkeleton v-if="loading" class="hidden min-w-0 flex-1 xl:block" />

        <!-- Compact item cards for phones and tablets -->
        <div v-else class="min-h-0 min-w-0 flex-1 space-y-1.5 overflow-y-auto p-2.5 xl:hidden min-[480px]:space-y-2 min-[480px]:p-3">
          <article
            v-for="row in filteredRows"
            :key="groupKey(row)"
            class="rounded-md border border-gray-200 bg-white p-2.5 dark:border-gray-800 dark:bg-gray-900 min-[480px]:p-3"
          >
            <div class="flex items-center gap-2 min-[480px]:gap-3">
              <input
                :checked="isGroupSelected(row)"
                type="checkbox"
                :aria-label="`Select ${row.item_name}`"
                class="h-3.5 w-3.5 shrink-0 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800 min-[480px]:h-4 min-[480px]:w-4"
                @change="toggleGroupSelection(row, $event.target.checked)"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white min-[480px]:text-base">{{ row.item_name }}</p>
                <p v-if="hasIssuedOut(row) && workspace !== 'available' && workspace !== 'all'" class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white min-[480px]:text-base"><span v-if="workspace !== 'assigned'">Assigned: </span>{{ format(assignedOutQty(row)) }} {{ row.unit }}</p>
                <p v-if="workspace === 'assigned' && rowAssignees(row)" class="mt-0.5 truncate text-[11px] font-medium text-gray-500 dark:text-gray-400 min-[480px]:text-xs">{{ rowAssignees(row) }}</p>
                <p v-else-if="row.status === 'available' && workspace !== 'all'" class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white min-[480px]:text-base">Available: {{ format(row.free_quantity ?? row.quantity) }} {{ row.unit }}</p>
                <p v-else class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 min-[480px]:mt-1 min-[480px]:text-xs">{{ format(row.quantity) }} {{ row.unit }}</p>
                <p v-if="hasIssuedOut(row) && workspace !== 'available' && workspace !== 'all' && (row.pending_hold ?? 0) > 0" class="mt-0.5 text-[11px] font-semibold text-amber-600 dark:text-amber-300 min-[480px]:text-xs">
                  Pending: {{ format(row.pending_hold ?? 0) }}
                </p>
                <p v-else-if="row.status === 'available'" class="mt-0.5 text-[11px] font-semibold min-[480px]:text-xs" :class="(row.pending_hold ?? 0) > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-gray-400 dark:text-gray-500'">
                  Pending: {{ format(row.pending_hold ?? 0) }}
                </p>
                <p v-if="groupSubtitle(row)" class="mt-0.5 truncate font-mono text-[11px] text-gray-500 dark:text-gray-400">
                  {{ groupSubtitle(row) }}
                </p>
              </div>
              <p v-if="workspace === 'disposed'" class="min-w-0 flex-1 truncate text-[11px] text-gray-500 dark:text-gray-400" :title="disposalReason(row) || 'No reason recorded.'">
                {{ disposalReason(row) || 'No reason recorded.' }}
              </p>
              <StatusBadge v-else :status="row.status" compact />
              <span v-if="hasIssuedOut(row) && workspace !== 'available' && workspace !== 'all'" class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">Assigned issuing</span>
              <button
                type="button"
                class="shrink-0 rounded-md border border-emerald-200 px-2 py-1.5 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10 min-[480px]:px-3 min-[480px]:py-2 min-[480px]:text-xs"
                :aria-expanded="recordGroup?.item_id === row.item_id"
                @click="openRecordModal(row)"
              >
                Show more
              </button>
            </div>
          </article>

          <div v-if="!filteredRows.length" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
            <p class="font-semibold text-gray-800 dark:text-white">No inventory groups found</p>
            <p class="mt-1 text-xs text-gray-400">Try adjusting your search or category filters.</p>
          </div>
        </div>

        <!-- Table -->
        <div v-if="!loading" class="hidden min-h-0 min-w-0 flex-1 overflow-auto custom-scrollbar xl:block" role="region" aria-label="Inventory table, scroll horizontally to view all columns" tabindex="0">
          <p class="sticky left-0 z-10 border-b border-gray-100 bg-white px-4 py-2 text-xs text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 sm:hidden">
            Scroll horizontally to view all columns
          </p>
          <table class="w-full min-w-[64rem] text-left text-xs">
            <thead class="sticky top-0 z-20 bg-gray-50 dark:bg-gray-800">
              <tr class="border-b border-gray-200 bg-gray-50/80 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-gray-800/40 dark:text-gray-400">
                <th class="w-10 px-4 py-3.5">
                  <input
                    v-if="filteredRows.length"
                    v-model="allChecked"
                    type="checkbox"
                    aria-label="Select all groups"
                    class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800"
                  />
                </th>
                <th class="px-4 py-3.5">Item & ICS</th>
                <th class="px-4 py-3.5">Category</th>
                <th class="px-4 py-3.5 text-right">Quantity</th>
                <th v-if="workspace === 'assigned'" class="px-4 py-3.5">Assignee</th>
                <th class="px-4 py-3.5 text-right">Unit Cost</th>
                <th class="px-4 py-3.5 text-right">Total Cost</th>
                <th v-if="workspace === 'disposed'" class="px-4 py-3.5">Reason</th>
                <th v-else class="px-4 py-3.5">Condition / Status</th>
                <th v-if="workspace !== 'all'" class="px-4 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
              <template v-for="row in filteredRows" :key="groupKey(row)">
                <tr
                  class="group transition-colors hover:bg-emerald-50/30 dark:hover:bg-emerald-950/10"
                  :class="{ 'bg-emerald-50/40 dark:bg-emerald-950/20': recordGroup?.item_id === row.item_id }"
                >
                  <!-- Checkbox -->
                  <td class="px-4 py-3.5">
                    <input
                      :checked="isGroupSelected(row)"
                      type="checkbox"
                      :aria-label="`Select ${row.item_name}`"
                      class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800"
                      @change="toggleGroupSelection(row, $event.target.checked)"
                    />
                  </td>

                  <!-- Item Name + ICS -->
                  <td class="px-4 py-3.5">
                    <div class="flex items-center gap-3">
                      <!-- Item Icon Badge -->
                      <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        role="img"
                        :aria-label="`${row.item_name} icon`"
                        v-html="itemIconMarkup(row)"
                      >
                      </div>
                      <div class="min-w-0">
                        <button
                          type="button"
                          class="font-semibold text-gray-900 transition-colors hover:text-emerald-600 dark:text-white dark:hover:text-emerald-400"
                          :aria-expanded="recordGroup?.item_id === row.item_id"
                          @click="openRecordModal(row)"
                        >
                          {{ row.item_name }}
                        </button>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                          <span
                            v-if="row.ics_no"
                            class="rounded-md bg-emerald-50 px-1.5 py-0.5 font-mono text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                          >
                            {{ row.ics_no }}
                          </span>
                          <span v-else class="text-[11px] text-gray-400 dark:text-gray-500">No ICS</span>
                          <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">·</span>
                          <span class="text-[11px] text-gray-500 dark:text-gray-400">
                            Acquired {{ formatDate(row.date_acquired) }}
                          </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                          <button
                            v-if="lifespanRemainingFraction(row) !== null"
                            type="button"
                            class="flex w-20 items-center rounded-full py-1 transition-opacity hover:opacity-75 focus-visible:outline-2 focus-visible:outline-emerald-500"
                            :title="lifespanBarTitle(row)"
                            :aria-label="`${lifespanBarTitle(row)}. Show lifespan details.`"
                            @click="openRecordModal(row)"
                          >
                            <span class="h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                              <span
                                class="block h-full min-w-[3px] rounded-full"
                                :class="lifespanBarFillClass(row)"
                                :style="{ width: lifespanBarWidth(row) }"
                              ></span>
                            </span>
                          </button>
                          <button
                            v-else-if="lifespanChip(row)"
                            type="button"
                            class="rounded-md px-1.5 py-0.5 font-medium transition-colors"
                            :class="lifespanChipClass(row)"
                            title="Show lifespan details"
                            @click="openRecordModal(row)"
                          >
                            {{ lifespanChip(row) }}
                          </button>
                          <span
                            v-for="meta in [groupMeta(row)]"
                            :key="meta.key"
                            class="flex flex-wrap items-center gap-1.5"
                          >
                          <span
                            v-if="meta.count > 1"
                            class="rounded-md bg-gray-100 px-1.5 py-0.5 font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300"
                          >
                            {{ meta.count }} units
                          </span>
                          <span
                            v-if="meta.invNo"
                            class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-gray-700 dark:bg-white/5 dark:text-gray-300"
                          >
                            {{ meta.invNo }}
                          </span>
                          <span
                            v-if="meta.serial"
                            class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-gray-600 dark:bg-white/5 dark:text-gray-400"
                          >
                            SN {{ meta.serial }}
                          </span>
                          </span>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Category -->
                  <td class="px-4 py-3.5">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                      {{ row.category?.category_name ?? 'Uncategorized' }}
                    </span>
                  </td>

                  <!-- Quantity -->
                  <td class="px-4 py-3.5 text-right font-bold text-gray-900 dark:text-white">
                    <template v-if="hasIssuedOut(row) && workspace !== 'available' && workspace !== 'all'">
                      <span v-if="workspace !== 'assigned'">Assigned: </span>{{ format(assignedOutQty(row)) }}
                      <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-0.5">{{ row.unit }}</span>
                      <span v-if="(row.pending_hold ?? 0) > 0" class="block text-[11px] font-semibold text-amber-600 dark:text-amber-300">
                        Pending: {{ format(row.pending_hold ?? 0) }}
                      </span>
                    </template>
                    <template v-else-if="row.status === 'available' && workspace !== 'all'">
                      Available: {{ format(row.free_quantity ?? row.quantity) }}
                      <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-0.5">{{ row.unit }}</span>
                      <span class="block text-[11px] font-semibold" :class="(row.pending_hold ?? 0) > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-gray-400 dark:text-gray-500'">
                        Pending: {{ format(row.pending_hold ?? 0) }}
                      </span>
                    </template>
                    <template v-else>
                      {{ format(row.quantity) }}
                      <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-0.5">{{ row.unit }}</span>
                    </template>
                  </td>

                  <!-- Assignee -->
                  <td v-if="workspace === 'assigned'" class="px-4 py-3.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                    {{ rowAssignees(row) || '—' }}
                  </td>

                  <!-- Unit Cost -->
                  <td class="px-4 py-3.5 text-right font-medium text-gray-600 dark:text-gray-300">
                    ₱{{ money(row.unit_cost) }}
                  </td>

                  <!-- Total Cost -->
                  <td class="px-4 py-3.5 text-right font-semibold text-gray-900 dark:text-white">
                    ₱{{ money(row.total_cost) }}
                  </td>

                  <!-- Status (disposed workspace shows the disposal reason instead) -->
                  <td class="px-4 py-3.5">
                    <div v-if="workspace === 'disposed'" class="max-w-64">
                      <p v-if="disposalReason(row)" class="truncate text-xs text-gray-700 dark:text-gray-300" :title="disposalReason(row)">
                        {{ disposalReason(row) }}
                      </p>
                      <p v-else class="text-[11px] text-gray-400 dark:text-gray-500">
                        No reason recorded.
                      </p>
                    </div>
                    <div v-else class="flex flex-wrap items-center gap-1.5">
                      <StatusBadge :status="displayStatus(row)" />
                    </div>
                  </td>

                  <!-- Actions: display-only under All Inventory -->
                  <td v-if="workspace !== 'all'" class="px-4 py-3.5 text-right">
                    <div class="inline-flex items-center gap-1.5">
                      <!-- Details Toggle -->
                      <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                        @click="openRecordModal(row)"
                      >
                        Records
                        <svg
                          class="h-3.5 w-3.5 transition-transform duration-200"
                          :class="{ 'rotate-180': recordGroup?.item_id === row.item_id }"
                          fill="none"
                          viewBox="0 0 24 24"
                          stroke="currentColor"
                          stroke-width="2"
                        >
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                      <button
                        v-if="displayStatus(row) === 'assigned'"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border border-indigo-200 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
                        title="Record the return of an assigned item"
                        @click="openReturnModal(row)"
                      >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-1" />
                        </svg>
                        Return
                      </button>
                      <button
                        v-if="row.status === 'available' && !isPartialAssigned(row) && hasMaintenanceEligibleItems(row)"
                        type="button"
                        class="rounded-md p-1.5 text-amber-700 hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-500/10"
                        title="Send selected items for repair"
                        aria-label="Send items for repair"
                        @click="openMaintenanceModal(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-7 7a2.1 2.1 0 0 1-3-3l7-7a6 6 0 0 1 7.9-7.9z" />
                        </svg>
                      </button>
                      <template v-if="row.status === 'under_repair'">
                        <button
                          type="button"
                          class="rounded-md p-1.5 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                          title="Mark selected items as repaired"
                          aria-label="Mark items as repaired"
                          @click="openMaintenanceActionModal(row, 'repair')"
                        >
                          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                            <circle cx="12" cy="12" r="10" />
                          </svg>
                        </button>
                        <button
                          type="button"
                          class="rounded-md p-1.5 text-orange-700 hover:bg-orange-50 dark:text-orange-300 dark:hover:bg-orange-500/10"
                          title="Mark selected items ready to dispose"
                          aria-label="Mark items ready to dispose"
                          @click="openMaintenanceActionModal(row, 'dispose')"
                        >
                          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7l1 14h12l1-14M9 7V4h6v3m-5 4v6m4-6v6" />
                          </svg>
                        </button>
                      </template>
                      <button
                        v-if="row.status === 'ready_to_dispose'"
                        type="button"
                        class="rounded-md p-1.5 text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                        title="Dispose selected items"
                        aria-label="Dispose items"
                        @click="openDisposeModal(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2m-9 0 1 15h8l1-15m-7 4v7m4-7v7" />
                        </svg>
                      </button>

                      <!-- Edit Button -->
                      <button
                        v-if="row.status === 'available' && !isPartialAssigned(row)"
                        type="button"
                        class="rounded-md p-1.5 text-gray-500 hover:bg-emerald-50 hover:text-emerald-700 dark:text-gray-400 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300"
                        title="Edit inventory group"
                        @click="askEdit(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                      </button>

                      <!-- Delete Button -->
                      <button
                        v-if="row.status === 'available' && !isPartialAssigned(row)"
                        type="button"
                        class="rounded-md p-1.5 text-gray-500 hover:bg-rose-50 hover:text-rose-700 dark:text-gray-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
                        title="Delete inventory group"
                        @click="askDelete(row)"
                      >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>

              <!-- Empty State -->
              <tr v-if="!filteredRows.length">
                <td :colspan="workspace === 'all' ? 7 : workspace === 'assigned' ? 9 : 8" class="px-4 py-16 text-center text-sm text-gray-500 dark:text-gray-400">
                  <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-md bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500 mb-3">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                  </div>
                  <p class="font-semibold text-gray-800 dark:text-white">No inventory groups found</p>
                  <p class="mt-1 text-xs text-gray-400">Try adjusting your search or category filters.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
          </div>
        </Transition>
        <section v-else class="flex min-h-0 flex-1 flex-col overflow-hidden">
          <div class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-100 p-3 dark:border-gray-800">
            <div>
              <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ workspace === 'investigations' ? 'Lost / Stolen Investigations' : 'Incident Reports' }}
              </h2>
              <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ displayedIncidentQueue.length }} {{ workspace === 'investigations' ? 'active investigation' : 'open report' }}{{ displayedIncidentQueue.length === 1 ? '' : 's' }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/5"
              :disabled="incidentQueueLoading"
              @click="loadIncidentQueue"
            >
              {{ incidentQueueLoading ? 'Refreshing…' : 'Refresh' }}
            </button>
          </div>
          <p v-if="incidentQueueError" role="alert" class="m-3 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
            {{ incidentQueueError }}
          </p>
          <div v-if="incidentQueueLoading && incidentQueue.length === 0" class="m-3 h-20 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
          <div v-else-if="displayedIncidentQueue.length === 0 && !incidentQueueError" class="m-3 flex flex-col items-center justify-center px-4 py-10 text-center">
            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-md text-gray-400 dark:text-gray-500">
              <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4" />
              </svg>
            </div>
            <p class="text-sm font-semibold text-gray-800 dark:text-white">
              {{ workspace === 'investigations' ? 'No active investigations' : 'No incident reports found' }}
            </p>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
              {{ workspace === 'investigations' ? 'Active Lost or Stolen investigations will appear here.' : 'Submitted incident reports will appear here.' }}
            </p>
          </div>
          <div v-else-if="displayedIncidentQueue.length > 0" class="grid min-h-0 flex-1 grid-cols-1 content-start gap-3 overflow-y-auto p-3 md:grid-cols-2 xl:grid-cols-4">
            <article
              v-for="report in displayedIncidentQueue"
              :key="report.id"
              class="w-full space-y-2 rounded-md border border-gray-200 p-3 dark:border-gray-700"
            >
              <div class="flex min-w-0 items-start justify-between gap-2">
                <div class="min-w-0">
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ report.item.name }}</h3>
                  <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                    {{ report.item.inventory_item_no || `Item #${report.item.id}` }}
                    · {{ report.quantity }} {{ report.item.unit || 'unit(s)' }} affected
                    · {{ report.reporter.name }}
                  </p>
                </div>
                <span class="shrink-0 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold capitalize text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                  {{ report.status.replaceAll('_', ' ') }}
                </span>
              </div>
              <div class="flex min-w-0 items-start gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                <span class="shrink-0 font-semibold capitalize">{{ report.type.replaceAll('_', ' ') }}</span>
                <span aria-hidden="true">·</span>
                <p class="line-clamp-2 min-w-0">{{ report.details }}</p>
              </div>
              <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-2 dark:border-gray-800">
                <p class="text-[10px] text-gray-500 dark:text-gray-400">
                  {{ formatIncidentDate(report.submitted_at) }}
                  <span v-if="report.timeline.length"> · {{ report.timeline.length }} updates</span>
                </p>
                <button
                  type="button"
                  class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
                  :class="incidentDetailsReport?.id === report.id
                    ? 'bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-sm hover:from-emerald-500 hover:to-teal-500'
                    : 'border border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/5'"
                  :aria-expanded="incidentDetailsReport?.id === report.id"
                  :aria-controls="incidentDetailsReport?.id === report.id ? 'incident-report-details-popover' : undefined"
                  @click="toggleIncidentDetails(report, $event)"
                >
                  Details
                </button>
              </div>
              <div v-if="report.status === 'reported' || (report.status === 'under_review' && !report.handover)" class="flex flex-wrap gap-1.5">
                <button
                  v-if="report.status === 'reported' || report.status === 'under_review'"
                  type="button"
                  class="rounded-md border border-gray-200 px-2 py-1 text-[10px] font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/5"
                  :disabled="incidentActionId !== null"
                  @click="requestIncidentInformation(report)"
                >
                  Request Details
                </button>
                <button
                  type="button"
                  class="rounded-md border border-rose-200 px-2 py-1 text-[10px] font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-500/10"
                  :disabled="incidentActionId !== null"
                  @click="declineIncident(report)"
                >
                  Decline
                </button>
                <button
                  v-if="report.status === 'reported'"
                  type="button"
                  class="rounded-md bg-brand-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                  :disabled="incidentActionId !== null"
                  @click="acceptIncident(report)"
                >
                  Accept for Review
                </button>
                <template v-if="report.status === 'under_review'">
                  <button
                    v-if="['damaged', 'needs_repair'].includes(report.type)"
                    type="button"
                    class="rounded-md border border-blue-200 px-2 py-1 text-[10px] font-semibold text-blue-800 hover:bg-blue-50 disabled:opacity-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-500/10"
                    :disabled="incidentActionId !== null || Boolean(report.handover)"
                    @click="recordIncidentInspection(report)"
                  >
                    Record Inspection
                  </button>
                  <button
                    v-if="['damaged', 'needs_repair'].includes(report.type) && report.inspection && ['repairable', 'not_repairable'].includes(report.inspection.repairability) && !report.handover"
                    type="button"
                    class="rounded-md border border-emerald-200 px-2 py-1 text-[10px] font-semibold text-emerald-800 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                    :disabled="incidentActionId !== null"
                    @click="confirmIncidentHandover(report)"
                  >
                    {{ report.inspection.repairability === 'not_repairable' ? 'Confirm Handover & Ready for Disposal' : 'Confirm Handover' }}
                  </button>
                  <button
                    v-if="['damaged', 'needs_repair'].includes(report.type) && report.inspection?.repairability === 'repairable' && report.handover"
                    type="button"
                    class="rounded-md bg-brand-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                    :disabled="incidentActionId !== null"
                    @click="startIncidentRepair(report)"
                  >
                    Start Repair
                  </button>
                  <button
                    v-if="['lost', 'stolen'].includes(report.type)"
                    type="button"
                    class="rounded-md bg-indigo-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="incidentActionId !== null"
                    @click="startIncidentInvestigation(report)"
                  >
                    Start Investigation
                  </button>
                </template>
              </div>
              <div v-else-if="report.status === 'processing_repair'" class="flex flex-wrap gap-1.5">
                <button
                  type="button"
                  class="rounded-md bg-brand-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                  :disabled="incidentActionId !== null"
                  @click="completeIncidentRepair(report)"
                >
                  Mark Repair Complete
                </button>
                <button
                  type="button"
                  class="rounded-md border border-orange-200 px-2 py-1 text-[10px] font-semibold text-orange-800 hover:bg-orange-50 disabled:opacity-50 dark:border-orange-800 dark:text-orange-300 dark:hover:bg-orange-500/10"
                  :disabled="incidentActionId !== null"
                  @click="confirmIncidentRepairNotViable(report)"
                >
                  Repair Not Viable
                </button>
              </div>
              <div v-else-if="report.status === 'under_investigation'" class="flex flex-wrap gap-1.5">
                <button
                  type="button"
                  class="rounded-md border border-indigo-200 px-2 py-1 text-[10px] font-semibold text-indigo-800 hover:bg-indigo-50 disabled:opacity-50 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
                  :disabled="incidentActionId !== null"
                  @click="openInvestigationActionModal(report)"
                >
                  Add Investigation Update
                </button>
                <button
                  type="button"
                  class="rounded-md bg-indigo-600 px-2 py-1 text-[10px] font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                  :disabled="incidentActionId !== null"
                  @click="openInvestigationResolutionModal(report)"
                >
                  Close Investigation
                </button>
              </div>
              <p v-else-if="report.status === 'processing_disposal'" class="text-[10px] text-orange-700 dark:text-orange-300">
                Complete disposal through Inventory.
              </p>
              <p v-else-if="report.status === 'under_review'" class="text-[10px] text-gray-500 dark:text-gray-400">
                {{ report.inspection?.repairability === 'pending' ? 'Inspection is pending.' : 'Inspection and handover are next.' }}
              </p>
              <p v-else-if="report.status === 'awaiting_reporter'" class="text-[10px] text-amber-700 dark:text-amber-300">
                Waiting for reporter information.
              </p>
            </article>
          </div>
          <Teleport to="body">
            <section
              v-if="incidentDetailsReport"
              id="incident-report-details-popover"
              ref="incidentDetailsPopover"
              role="dialog"
              :aria-labelledby="`incident-details-title-${incidentDetailsReport.id}`"
              class="fixed z-[100000] flex w-[min(48rem,calc(100vw-1.5rem))] max-h-[min(78vh,42rem)] flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-2xl ring-1 ring-black/5 dark:border-gray-700 dark:bg-gray-900"
              :style="incidentDetailsStyle"
              @pointerdown.stop
              @click.stop
            >
              <header class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                <div class="min-w-0">
                  <h3 :id="`incident-details-title-${incidentDetailsReport.id}`" class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                    {{ incidentDetailsReport.item.name }}
                  </h3>
                  <div class="mt-1 flex flex-nowrap items-center gap-x-3 overflow-hidden text-[11px] text-gray-500 dark:text-gray-400">
                    <p class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap">
                      <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 9h8M8 13h5" />
                      </svg>
                      {{ incidentDetailsReport.item.inventory_item_no || `Item #${incidentDetailsReport.item.id}` }}
                    </p>
                    <p class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap">
                      <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9-4 9 4v10l-9 4-9-4z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 4 9-4m-9 4v10" />
                      </svg>
                      {{ incidentDetailsReport.quantity }} {{ incidentDetailsReport.item.unit || 'unit(s)' }}
                    </p>
                    <p class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap">
                      <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="8" r="3.5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 20a7 7 0 0 1 14 0" />
                      </svg>
                      {{ incidentDetailsReport.reporter.name }}
                    </p>
                  </div>
                </div>
                <button
                  ref="incidentDetailsCloseButton"
                  type="button"
                  class="shrink-0 rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                  aria-label="Close incident report details"
                  @click="closeIncidentDetails(true)"
                >
                  <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                  </svg>
                </button>
              </header>
              <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 overflow-hidden p-4 text-xs text-gray-700 dark:text-gray-300 md:grid-cols-2">
                <div class="min-h-0 space-y-4 overflow-y-auto md:border-r md:border-gray-100 md:pr-4 dark:md:border-gray-800">
                  <section class="space-y-2">
                    <div class="space-y-1">
                      <p><span class="font-semibold">Status:</span> <span class="capitalize">{{ incidentDetailsReport.status.replaceAll('_', ' ') }}</span></p>
                      <p><span class="font-semibold">Type:</span> <span class="capitalize">{{ incidentDetailsReport.type.replaceAll('_', ' ') }}</span></p>
                      <p><span class="font-semibold">Reported:</span> {{ formatIncidentDate(incidentDetailsReport.submitted_at) }}</p>
                    </div>
                    <div>
                      <p class="font-semibold">Incident details</p>
                      <p class="mt-1 whitespace-pre-wrap">{{ incidentDetailsReport.details }}</p>
                    </div>
                  </section>
                  <section v-if="incidentDetailsReport.evidence.length" class="space-y-1 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <h4 class="font-semibold">Evidence</h4>
                    <a
                      v-for="file in incidentDetailsReport.evidence"
                      :key="file.id"
                      :href="file.download_url"
                      class="block font-semibold text-brand-700 underline dark:text-brand-300"
                    >
                      Download {{ file.filename }}
                    </a>
                  </section>
                  <section v-if="incidentDetailsReport.inspection" class="space-y-1 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <h4 class="font-semibold capitalize">
                      Inspection · {{ incidentDetailsReport.inspection.repairability.replaceAll('_', ' ') }}
                    </h4>
                    <p v-if="incidentDetailsReport.inspection.inspector" class="inline-flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400">
                      <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="12" cy="8" r="3.5" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 20a7 7 0 0 1 14 0" />
                      </svg>
                      {{ incidentDetailsReport.inspection.inspector }}
                    </p>
                    <p class="whitespace-pre-wrap">{{ incidentDetailsReport.inspection.findings }}</p>
                  </section>
                  <section v-if="incidentDetailsReport.handover" class="space-y-1 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <h4 class="font-semibold">Handover received · {{ incidentDetailsReport.quantity }} affected unit(s)</h4>
                    <p>{{ incidentDetailsReport.handover.details }}</p>
                    <p v-if="incidentDetailsReport.handover.processing_inventory_id" class="text-[11px] text-gray-500">
                      Separate inventory record used to track the affected units during repair or disposal.
                    </p>
                  </section>
                </div>
                <section class="min-h-0 md:pl-1">
                  <h4 class="mb-2 font-semibold">Timeline · {{ incidentDetailsReport.timeline.length }} updates</h4>
                  <ol
                    v-if="incidentDetailsReport.timeline.length"
                    class="space-y-3"
                    :class="incidentDetailsReport.timeline.length >= 3 ? 'max-h-48 overflow-y-auto pr-2' : ''"
                  >
                    <li
                      v-for="(event, index) in incidentDetailsReport.timeline"
                      :key="event.id ?? `${incidentDetailsReport.id}-${index}`"
                      class="relative pl-6"
                    >
                      <span
                        v-if="index < incidentDetailsReport.timeline.length - 1"
                        aria-hidden="true"
                        class="absolute bottom-[-0.75rem] left-[5px] top-2 w-0.5 bg-emerald-500"
                      />
                      <span
                        aria-hidden="true"
                        class="absolute left-0 top-1 h-3 w-3 rounded-full border-2 ring-2 ring-white dark:ring-gray-900"
                        :class="index === incidentDetailsReport.timeline.length - 1
                          ? 'border-emerald-600 bg-white dark:border-emerald-400 dark:bg-gray-900'
                          : 'border-emerald-600 bg-emerald-600 dark:border-emerald-400 dark:bg-emerald-400'"
                      />
                      <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                        <p class="inline-flex items-center gap-1">
                          <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
                          </svg>
                          {{ formatIncidentDate(event.created_at) }}
                        </p>
                        <p class="inline-flex items-center gap-1">
                          <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="12" cy="8" r="3.5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 20a7 7 0 0 1 14 0" />
                          </svg>
                          {{ event.actor || 'System' }}
                        </p>
                      </div>
                      <p class="mt-0.5 font-semibold">{{ incidentEventLabel(event) }}</p>
                      <p v-if="event.details" class="mt-0.5 whitespace-pre-wrap">{{ event.details }}</p>
                      <div v-if="incidentRelatedRecords(event).length" class="mt-2 space-y-1.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                          Related records
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                          <span
                            v-for="reference in incidentRelatedRecords(event)"
                            :key="`${reference.label}-${reference.id}`"
                            class="inline-flex max-w-full flex-col rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-[10px] text-gray-700 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300"
                          >
                            <span>{{ reference.description }}</span>
                          </span>
                        </div>
                      </div>
                      <a
                        v-for="file in investigationEvidenceForEvent(incidentDetailsReport, event)"
                        :key="`${event.id}-${file.id}`"
                        :href="file.download_url"
                        class="mt-1 block font-semibold text-brand-700 underline dark:text-brand-300"
                      >
                        {{ file.filename }}
                      </a>
                    </li>
                  </ol>
                  <p v-else class="text-gray-500 dark:text-gray-400">No timeline entries yet.</p>
                </section>
              </div>
            </section>
          </Teleport>
        </section>
        <InventoryItemLogDrawer
          :open="logsOpen"
          :categories="categories"
          @close="logsOpen = false"
        />

        <!-- Pagination & Footer -->
        <div v-if="!isIncidentWorkspace" class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-gray-200/80 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-900/50">
          <Pagination
            class="ml-auto"
            :current-page="paginator.current_page ?? 1"
            :last-page="paginator.last_page ?? 1"
            :from="paginator.from"
            :to="paginator.to"
            :total="paginator.total ?? 0"
            @page="goToPage"
          />
        </div>
      </div>
    </template>

    <Modal
      :open="incidentQueueOpen"
      title="Incident Report Review"
      subtitle="Review reports from end users. Reporting itself does not change inventory or assignment status."
      max-width="max-w-5xl"
      fixed-body
      @close="incidentQueueOpen = false"
    >
      <div class="flex min-h-0 flex-1 flex-col gap-3">
        <div class="flex shrink-0 items-center justify-between gap-3">
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ incidentQueue.length }} open report{{ incidentQueue.length === 1 ? '' : 's' }}
          </p>
          <button
            type="button"
            class="rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/5"
            :disabled="incidentQueueLoading"
            @click="loadIncidentQueue"
          >
            Refresh
          </button>
        </div>
        <p v-if="incidentQueueError" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
          {{ incidentQueueError }}
        </p>
        <div v-if="incidentQueueLoading" class="compact-skeleton h-20" />
        <p v-else-if="!incidentQueueError && incidentQueue.length === 0" class="rounded-md bg-gray-50 px-3 py-8 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">
          There are no open incident reports to review.
        </p>
        <div v-else class="min-h-0 space-y-3 overflow-y-auto pr-1">
          <article
            v-for="report in incidentQueue"
            :key="report.id"
            class="space-y-3 rounded-md border border-gray-200 p-3 dark:border-gray-700"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ report.item.name }}</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                  {{ report.item.inventory_item_no || `Item #${report.item.id}` }}
                  · {{ report.quantity }} {{ report.item.unit || 'unit(s)' }} affected
                  · {{ report.reporter.name }}
                </p>
              </div>
              <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold capitalize text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                {{ report.status.replaceAll('_', ' ') }}
              </span>
            </div>
            <div class="grid gap-3 text-xs text-gray-700 dark:text-gray-300 sm:grid-cols-2">
              <p><span class="font-semibold">Type:</span> {{ report.type.replaceAll('_', ' ') }}</p>
              <p><span class="font-semibold">Reported:</span> {{ formatIncidentDate(report.submitted_at) }}</p>
              <p class="sm:col-span-2 whitespace-pre-wrap">{{ report.details }}</p>
            </div>
            <div v-if="report.evidence.length" class="flex flex-wrap gap-2 border-t border-gray-100 pt-2 dark:border-gray-800">
              <a
                v-for="file in report.evidence"
                :key="file.id"
                :href="file.download_url"
                class="text-xs font-semibold text-brand-700 underline dark:text-brand-300"
              >
                Download {{ file.filename }}
              </a>
            </div>
            <ol v-if="report.timeline.length" class="max-h-32 space-y-0 overflow-y-auto border-t border-gray-100 pt-2 pr-1 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
              <li
                v-for="(event, index) in report.timeline"
                :key="event.id ?? `${report.id}-${index}`"
                class="relative flex gap-2.5 pb-3 last:pb-0"
              >
                <span class="relative flex w-3 shrink-0 justify-center">
                  <span
                    v-if="index < report.timeline.length - 1"
                    class="absolute -bottom-5 top-2 w-0.5 bg-emerald-600/70 dark:bg-emerald-400/70"
                  />
                  <span class="relative z-10 mt-1 h-2 w-2 rounded-full bg-brand-500 ring-2 ring-white dark:ring-gray-900" />
                </span>
                <p class="min-w-0 flex-1">
                  {{ formatIncidentDate(event.created_at) }} · {{ event.actor || 'System' }} ·
                  {{ incidentEventLabel(event) }}
                  <span v-if="event.details"> — {{ event.details }}</span>
                  <span v-if="incidentRelatedRecords(event).length" class="block text-[10px] text-gray-400 dark:text-gray-500">
                    Related:
                    <span v-for="(reference, referenceIndex) in incidentRelatedRecords(event)" :key="`${reference.label}-${reference.id}`">
                      <span v-if="referenceIndex"> · </span>
                      <span>{{ reference.description }}</span>
                    </span>
                  </span>
                </p>
              </li>
            </ol>
            <div v-if="report.inspection" class="rounded-md bg-blue-50 px-3 py-2 text-xs text-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
              <p class="font-semibold">
                Inspection: {{ report.inspection.repairability.replaceAll('_', ' ') }}
                <span v-if="report.inspection.inspector">· {{ report.inspection.inspector }}</span>
              </p>
              <p class="mt-1 whitespace-pre-wrap">{{ report.inspection.findings }}</p>
            </div>
            <div v-if="report.handover" class="rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">
              <p class="font-semibold">Handover received · {{ report.quantity }} affected unit(s)</p>
              <p class="mt-1">{{ report.handover.details }}</p>
              <p class="mt-1">
                Separate inventory record used to track the affected units during repair or disposal.
              </p>
            </div>
            <div v-if="['reported', 'under_review'].includes(report.status)" class="flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-2 dark:border-gray-800">
              <button
                type="button"
                class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/5"
                :disabled="incidentActionId !== null"
                @click="requestIncidentInformation(report)"
              >
                Request Details
              </button>
              <button
                v-if="report.status === 'reported' || (report.status === 'under_review' && !report.handover)"
                type="button"
                class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-500/10"
                :disabled="incidentActionId !== null"
                @click="declineIncident(report)"
              >
                Decline
              </button>
              <button
                v-if="report.status === 'reported'"
                type="button"
                class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="incidentActionId !== null"
                @click="acceptIncident(report)"
              >
                {{ incidentActionId === report.id ? 'Accepting…' : 'Accept for Review' }}
              </button>
              <template v-if="report.status === 'under_review'">
                <button
                  type="button"
                  class="rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-50 disabled:opacity-50 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-500/10"
                  :disabled="incidentActionId !== null || Boolean(report.handover)"
                  @click="recordIncidentInspection(report)"
                >
                  Record Inspection
                </button>
                <button
                  v-if="report.inspection && ['repairable', 'not_repairable'].includes(report.inspection.repairability) && !report.handover"
                  type="button"
                  class="rounded-md border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-50 disabled:opacity-50 dark:border-emerald-800 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                  :disabled="incidentActionId !== null"
                  @click="confirmIncidentHandover(report)"
                >
                  {{ report.inspection.repairability === 'not_repairable' ? 'Confirm Handover & Ready for Disposal' : 'Confirm Handover' }}
                </button>
                <button
                  v-if="report.inspection?.repairability === 'repairable' && report.handover"
                  type="button"
                  class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                  :disabled="incidentActionId !== null"
                  @click="startIncidentRepair(report)"
                >
                  Start Repair
                </button>
              </template>
            </div>
            <p
              v-if="report.status === 'under_review' && report.inspection?.repairability === 'pending'"
              class="text-xs text-amber-700 dark:text-amber-300"
            >
              Inspection is inconclusive. Add another inspection before handover or repair processing.
            </p>
            <div v-else-if="report.status === 'awaiting_reporter'" class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-2">
              <p class="text-xs text-amber-700 dark:text-amber-300">
                Waiting for the reporter to provide the requested information.
              </p>
              <button
                v-if="report.status === 'reported' || (report.status === 'under_review' && !report.handover)"
                type="button"
                class="rounded-md border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-500/10"
                :disabled="incidentActionId !== null"
                @click="declineIncident(report)"
              >
                Decline
              </button>
            </div>
            <div v-else-if="report.status === 'processing_repair'" class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-2">
              <p class="text-xs text-gray-600 dark:text-gray-300">
                Repair is in progress for the isolated affected units.
              </p>
              <button
                type="button"
                class="rounded-md border border-orange-200 px-3 py-1.5 text-xs font-semibold text-orange-800 hover:bg-orange-50 disabled:opacity-50 dark:border-orange-800 dark:text-orange-300 dark:hover:bg-orange-500/10"
                :disabled="incidentActionId !== null"
                @click="confirmIncidentRepairNotViable(report)"
              >
                Repair Not Viable
              </button>
              <button
                type="button"
                class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="incidentActionId !== null"
                @click="completeIncidentRepair(report)"
              >
                Mark Repair Complete
              </button>
            </div>
            <p v-else-if="report.status === 'processing_disposal'" class="border-t border-gray-100 pt-2 text-xs text-orange-800 dark:border-gray-800 dark:text-orange-300">
              The affected units are in Ready to Dispose. Complete disposal through the existing inventory disposal workflow.
            </p>
            <p v-else class="border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
              Accepted for review. The case stays open while inspection, handover, or other next steps are pending.
            </p>
          </article>
        </div>
      </div>
    </Modal>

    <Modal
      :open="incidentInspectionReport !== null"
      :title="incidentInspectionReport ? `Record inspection · ${incidentInspectionReport.item.name}` : 'Record inspection'"
      subtitle="Document what you found and whether the affected units can be repaired."
      max-width="max-w-lg"
      @close="closeIncidentInspectionModal"
    >
      <div v-if="incidentInspectionReport" class="space-y-4">
        <div class="space-y-1.5">
          <label for="incident-inspection-findings" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Inspection findings <span class="text-rose-500">*</span>
          </label>
          <textarea
            id="incident-inspection-findings"
            v-model="incidentInspectionFindings"
            rows="4"
            maxlength="2000"
            required
            :disabled="incidentActionId !== null"
            placeholder="Describe the physical condition and inspection results"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>
        <div class="space-y-1.5">
          <label for="incident-inspection-repairability" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Repairability <span class="text-rose-500">*</span>
          </label>
          <select
            id="incident-inspection-repairability"
            v-model="incidentInspectionRepairability"
            required
            :disabled="incidentActionId !== null"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          >
            <option value="pending">Pending / needs more inspection</option>
            <option value="repairable">Repairable</option>
            <option value="not_repairable">Not repairable</option>
          </select>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="incidentActionId !== null"
            @click="closeIncidentInspectionModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="incidentActionId !== null || incidentInspectionFindings.trim().length < 3"
            @click="saveIncidentInspection"
          >
            {{ incidentActionId === incidentInspectionReport?.id ? 'Saving…' : 'Save Inspection' }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="incidentInformationReport !== null"
      :title="incidentInformationReport ? `Request details · ${incidentInformationReport.item.name}` : 'Request details'"
      subtitle="Tell the reporter what additional information is needed."
      max-width="max-w-lg"
      @close="closeIncidentInformationModal"
    >
      <div v-if="incidentInformationReport" class="space-y-1.5">
        <label for="incident-information-reason" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
          Information requested <span class="text-rose-500">*</span>
        </label>
        <textarea
          id="incident-information-reason"
          v-model="incidentInformationReason"
          rows="4"
          maxlength="1000"
          required
          :disabled="incidentActionId !== null"
          placeholder="Describe the details or evidence the reporter should provide"
          class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        />
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="incidentActionId !== null"
            @click="closeIncidentInformationModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="incidentActionId !== null || incidentInformationReason.trim().length < 3"
            @click="saveIncidentInformationRequest"
          >
            {{ incidentActionId === incidentInformationReport?.id ? 'Sending…' : 'Request Information' }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="incidentHandoverReport !== null"
      :title="incidentHandoverReport ? `Confirm handover · ${incidentHandoverReport.item.name}` : 'Confirm handover'"
      :subtitle="incidentHandoverReport ? `Confirm receipt of ${incidentHandoverReport.quantity} affected unit(s) from ${incidentHandoverReport.reporter.name}. Only these units will be processed.` : ''"
      max-width="max-w-lg"
      @close="closeIncidentHandoverModal"
    >
      <div v-if="incidentHandoverReport" class="space-y-1.5">
        <label for="incident-handover-details" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
          Handover notes <span class="text-rose-500">*</span>
        </label>
        <textarea
          id="incident-handover-details"
          v-model="incidentHandoverDetails"
          rows="4"
          maxlength="1000"
          required
          :disabled="incidentActionId !== null"
          placeholder="Record who handed over the units, where they were received, or other handover details"
          class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
        />
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="incidentActionId !== null"
            @click="closeIncidentHandoverModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="incidentActionId !== null || incidentHandoverDetails.trim().length < 3"
            @click="saveIncidentHandover"
          >
            {{ incidentActionId === incidentHandoverReport?.id ? 'Confirming…' : 'Confirm Handover' }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="investigationActionReport !== null"
      :title="investigationActionReport ? `Add investigation update · ${investigationActionReport.item.name}` : 'Add investigation update'"
      subtitle="Each update and evidence file is added to the permanent incident history."
      max-width="max-w-lg"
      @close="closeInvestigationActionModal"
    >
      <div v-if="investigationActionReport" class="space-y-4">
        <div class="space-y-1.5">
          <label for="investigation-action-details" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Investigation update <span class="text-rose-500">*</span>
          </label>
          <textarea
            id="investigation-action-details"
            v-model="investigationActionDetails"
            rows="4"
            maxlength="2000"
            required
            :disabled="incidentActionId !== null"
            placeholder="Record the action taken, who was contacted, or what was found"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>
        <div class="space-y-1.5">
          <label for="investigation-evidence" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Supporting evidence <span class="font-normal text-gray-500">(optional; up to 5 files, 5 MB each)</span>
          </label>
          <input
            id="investigation-evidence"
            type="file"
            multiple
            accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
            :disabled="incidentActionId !== null"
            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-gray-700 dark:text-gray-300 dark:file:bg-gray-800 dark:file:text-gray-200"
            @change="selectInvestigationEvidence"
          />
          <ul v-if="investigationEvidenceFiles.length" class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
            <li v-for="file in investigationEvidenceFiles" :key="`${file.name}-${file.size}-${file.lastModified}`">
              {{ file.name }}
            </li>
          </ul>
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="incidentActionId !== null"
            @click="closeInvestigationActionModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="incidentActionId !== null || investigationActionDetails.trim().length < 3"
            @click="saveInvestigationAction"
          >
            {{ incidentActionId === investigationActionReport?.id ? 'Saving…' : 'Add Update' }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="investigationResolutionReport !== null"
      :title="investigationResolutionReport ? `Close investigation · ${investigationResolutionReport.item.name}` : 'Close investigation'"
      :subtitle="investigationResolutionReport ? `This confirms the outcome for ${investigationResolutionReport.quantity} affected unit(s). Unaffected units will remain unchanged.` : ''"
      max-width="max-w-lg"
      @close="closeInvestigationResolutionModal"
    >
      <div v-if="investigationResolutionReport" class="space-y-4">
        <div class="space-y-1.5">
          <label for="investigation-outcome" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Confirmed outcome <span class="text-rose-500">*</span>
          </label>
          <select
            id="investigation-outcome"
            v-model="investigationResolutionOutcome"
            required
            :disabled="incidentActionId !== null"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          >
            <option value="recovered">Recovered — keep affected quantity assigned to reporter</option>
            <option value="confirmed_lost_stolen">Confirmed Lost/Stolen — remove affected quantity from inventory and assignment</option>
            <option value="unsubstantiated">Unsubstantiated — no inventory or assignment changes</option>
          </select>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Only the reported quantity is affected. This decision closes the investigation and is recorded in its timeline.
          </p>
        </div>
        <div class="space-y-1.5">
          <label for="investigation-resolution-details" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Resolution notes <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="investigation-resolution-details"
            v-model="investigationResolutionDetails"
            rows="3"
            maxlength="1000"
            :disabled="incidentActionId !== null"
            placeholder="Add the reason or supporting context for this outcome"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="incidentActionId !== null"
            @click="closeInvestigationResolutionModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="incidentActionId !== null || !investigationResolutionOutcome"
            @click="saveInvestigationResolution"
          >
            {{ incidentActionId === investigationResolutionReport?.id ? 'Closing…' : 'Confirm Outcome & Close' }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="recordGroup !== null"
      :title="recordGroup ? `${recordGroup.item_name} details` : 'Inventory details'"
      :subtitle="recordGroup ? `${recordGroup.sourceItems?.length ?? 0} source unit record(s) · ${recordGroup.category?.category_name ?? 'Uncategorized'}` : ''"
      max-width="max-w-4xl"
      fixed-body
      @close="recordGroup = null"
    >
      <div v-if="recordGroup" class="flex min-h-0 flex-1 flex-col space-y-3">
        <section class="shrink-0 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
          <div class="flex flex-wrap items-start justify-between gap-2.5">
            <div>
              <h3 class="font-semibold text-sm text-gray-900 dark:text-white">{{ recordGroup.item_name }}</h3>
              <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                {{ recordGroup.category?.category_name ?? 'Uncategorized' }}
                <span class="mx-1" aria-hidden="true">·</span>
                {{ recordGroup.ics_no ? `ICS: ${recordGroup.ics_no}` : 'No ICS' }}
              </p>
            </div>
            <StatusBadge :status="displayStatus(recordGroup)" />
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-gray-200 pt-2.5 text-xs dark:border-gray-700 sm:grid-cols-3">
            <div>
              <dt class="text-[11px] text-gray-500 dark:text-gray-400">Quantity</dt>
              <dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">{{ format(recordGroup.quantity) }} {{ recordGroup.unit }}</dd>
            </div>
            <div>
              <dt class="text-[11px] text-gray-500 dark:text-gray-400">Unit Cost</dt>
              <dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">₱{{ money(recordGroup.unit_cost) }}</dd>
            </div>
            <div>
              <dt class="text-[11px] text-gray-500 dark:text-gray-400">Total Cost</dt>
              <dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">₱{{ money(recordGroup.total_cost) }}</dd>
            </div>
            <div v-if="lifespanLabel(recordGroup) || lifespanEndLabel(recordGroup)">
              <dt class="text-[11px] text-gray-500 dark:text-gray-400">Lifespan</dt>
              <dd class="mt-0.5 font-semibold text-gray-800 dark:text-gray-200">
                <span v-if="lifespanLabel(recordGroup)">{{ lifespanLabel(recordGroup) }}</span>
                <span v-if="lifespanLabel(recordGroup) && lifespanEndLabel(recordGroup)" class="mx-1" aria-hidden="true">·</span>
                <span v-if="lifespanEndLabel(recordGroup)" :class="lifespanEndClass(recordGroup)">
                  {{ lifespanEndLabel(recordGroup) }}
                </span>
              </dd>
            </div>
          </dl>
          <div class="mt-3 flex shrink-0 flex-wrap gap-1.5 border-t border-gray-200 pt-2.5 dark:border-gray-700 xl:hidden">
            <button
              v-if="displayStatus(recordGroup) === 'assigned'"
              type="button"
              class="rounded-md border border-indigo-200 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
              @click="openReturnModal(recordGroup)"
            >
              Return
            </button>
            <button
              v-if="recordGroup.status === 'available' && !isPartialAssigned(recordGroup) && hasMaintenanceEligibleItems(recordGroup)"
              type="button"
              class="rounded-md border border-amber-200 px-2.5 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50 dark:border-amber-500/30 dark:text-amber-300 dark:hover:bg-amber-500/10"
              @click="openMaintenanceModal(recordGroup)"
            >
              Send for Repair
            </button>
            <template v-if="recordGroup.status === 'under_repair'">
              <button
                type="button"
                class="rounded-md border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                @click="openMaintenanceActionModal(recordGroup, 'repair')"
              >
                Mark Repaired
              </button>
              <button
                type="button"
                class="rounded-md border border-orange-200 px-2.5 py-1.5 text-xs font-semibold text-orange-700 hover:bg-orange-50 dark:border-orange-500/30 dark:text-orange-300 dark:hover:bg-orange-500/10"
                @click="openMaintenanceActionModal(recordGroup, 'dispose')"
              >
                Ready to Dispose
              </button>
            </template>
            <button
              v-if="recordGroup.status === 'ready_to_dispose'"
              type="button"
              class="rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10"
              @click="openDisposeModal(recordGroup)"
            >
              Dispose
            </button>
            <button
              v-if="recordGroup.status === 'available' && !isPartialAssigned(recordGroup)"
              type="button"
              class="rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/5"
              @click="askEdit(recordGroup)"
            >
              Edit
            </button>
            <button
              v-if="recordGroup.status === 'available' && !isPartialAssigned(recordGroup)"
              type="button"
              class="rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300 dark:hover:bg-rose-500/10"
              @click="askDelete(recordGroup)"
            >
              Delete
            </button>
          </div>
        </section>

        <div
          v-if="qrSources.length"
          class="flex shrink-0 flex-wrap items-center justify-between gap-2 rounded-md border border-blue-200 bg-blue-50/70 p-2.5 dark:border-blue-500/30 dark:bg-blue-500/5"
        >
          <label class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
            <input
              type="checkbox"
              :checked="allPrintChecked"
              aria-label="Select all QR codes"
              class="h-3.5 w-3.5 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
              @change="toggleAllPrint($event.target.checked)"
            />
            Select all QR codes
          </label>
          <button
            type="button"
            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="selectedPrintItemIds.length === 0 || printingQr"
            @click="openPrintSelected"
          >
            <LucideIcon :icon="Printer" class="h-3.5 w-3.5" />
            {{ printingQr ? 'Preparing labels…' : `Print selected (${selectedPrintItemIds.length})` }}
          </button>
        </div>

        <template v-if="recordGroup.status === 'disposed'">
          <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
            Disposal history and identifiers for each disposed unit.
          </p>
          <div class="min-h-[6rem] space-y-2 overflow-y-auto">
            <article
              v-for="source in recordGroup.sourceItems ?? []"
              :key="source.item_id"
              class="rounded-md border border-gray-200 p-3 dark:border-gray-700"
            >
              <div class="flex flex-wrap items-start justify-between gap-2.5">
                <div class="flex items-start gap-2.5">
                  <input
                    v-if="source.qr_code"
                    v-model="selectedPrintItemIds"
                    :value="source.item_id"
                    type="checkbox"
                    :aria-label="`Select QR code for ${source.inventory_item_no ?? `item ${source.item_id}`}`"
                    class="mt-1 h-3.5 w-3.5 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                  />
                  <div class="space-y-0.5">
                    <p class="font-mono text-xs font-bold text-gray-900 dark:text-white">
                      {{ source.inventory_item_no ?? `#${source.item_id}` }}
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                      Serial: <span class="font-mono">{{ source.serial_number || 'Not recorded' }}</span>
                    </p>
                    <p class="text-xs text-gray-600 dark:text-gray-300">
                      Quantity: {{ format(source.quantity) }} {{ source.unit }}
                    </p>
                  </div>
                </div>
                <span class="inline-flex rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                  Disposed
                </span>
              </div>

              <dl class="mt-3 grid gap-2 border-t border-gray-100 pt-2.5 text-xs dark:border-gray-700 sm:grid-cols-2">
                <div>
                  <dt class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Disposed on</dt>
                  <dd class="mt-0.5 text-gray-800 dark:text-gray-200">
                    {{ formatMovementDate(source.disposalMovement ?? source.latestDisposalMovement ?? source.disposal_movement ?? source.latest_disposal_movement) }}
                  </dd>
                </div>
                <div class="sm:col-span-2">
                  <dt class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Reason / notes</dt>
                  <dd class="mt-0.5 whitespace-pre-wrap text-gray-800 dark:text-gray-200">
                    {{ (source.disposalMovement ?? source.latestDisposalMovement ?? source.disposal_movement ?? source.latest_disposal_movement)?.notes || 'No reason or notes recorded.' }}
                  </dd>
                </div>
              </dl>
            </article>
          </div>
        </template>

        <template v-else>
          <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              Storage: {{ recordGroup.building || 'Main Building' }}{{ recordGroup.room ? `, Room ${recordGroup.room}` : '' }}
            </p>
          </div>

          <div class="min-h-[6rem] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
            <div
              v-for="source in recordGroup.sourceItems ?? []"
              :key="source.item_id"
              class="flex flex-wrap items-center justify-between gap-2 p-2.5 text-xs"
            >
              <div class="flex flex-wrap items-center gap-2">
                <input
                  v-if="source.qr_code"
                  v-model="selectedPrintItemIds"
                  :value="source.item_id"
                  type="checkbox"
                  :aria-label="`Select QR code for ${source.inventory_item_no ?? `item ${source.item_id}`}`"
                  class="h-3.5 w-3.5 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                />
                <span class="font-mono font-bold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span
                  v-if="source.serial_number"
                  class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-600 dark:bg-white/5 dark:text-gray-300"
                >
                  SN: {{ source.serial_number }}
                </span>
                <span v-else class="text-[11px] text-gray-400">No serial</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">
                  {{ format(sourceQuantity(source)) }} {{ source.unit }}
                </span>
              </div>

              <div class="flex flex-wrap items-center gap-1.5">
                <span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                  {{ source.active_assignee_summary || source.assigned_to?.full_name || source.assignedTo?.full_name || source.assigned_to?.username || source.assignedTo?.username || 'Stockroom' }}
                </span>
                <span
                  v-if="(source.receive_return_assignments ?? []).length"
                  class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                >
                  {{ source.receive_return_assignments.length }} pending return{{ source.receive_return_assignments.length > 1 ? 's' : '' }}
                </span>
                <div
                  v-if="openIssueReport(source)"
                  class="flex w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-md border border-rose-200 bg-rose-50/70 px-2 py-1.5 dark:border-rose-500/30 dark:bg-rose-500/5"
                >
                  <span class="text-[11px] font-bold capitalize text-rose-800 dark:text-rose-200">Reported {{ openIssueReport(source).diagnosis }}</span>
                  <span class="text-[11px] text-rose-700 dark:text-rose-300">
                    by {{ openIssueReport(source).reporter }}<span v-if="openIssueReport(source).notes"> · {{ openIssueReport(source).notes }}</span>
                  </span>
                  <a
                    v-for="file in openIssueReport(source).evidence"
                    :key="file.id"
                    :href="`/api/incident-evidence/${file.id}`"
                    class="text-[11px] font-semibold text-brand-700 underline dark:text-brand-300"
                  >
                    Download {{ file.original_filename }}
                  </a>
                  <span class="ml-auto inline-flex items-center gap-1.5">
                    <button
                      type="button"
                      class="rounded-md border border-gray-200 px-2 py-1 text-[11px] font-semibold text-gray-600 hover:bg-gray-100 disabled:opacity-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-white/5"
                      :disabled="reportDecisionId !== null"
                      @click="declineIssueReport(source)"
                    >
                      {{ reportDecisionId === openIssueReport(source).id ? 'Declining…' : 'Decline' }}
                    </button>
                  </span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </Modal>

    <Modal
      :open="maintenanceGroup !== null"
      :title="maintenanceGroup ? `Send ${maintenanceGroup.item_name} for repair` : 'Send items for repair'"
      subtitle="Select the available item records to send, then describe the issue."
      max-width="max-w-xl"
      fixed-body
      @close="closeMaintenanceModal"
    >
      <div v-if="maintenanceGroup" class="flex min-h-0 flex-1 flex-col space-y-3">
        <p
          v-if="maintenanceError"
          role="alert"
          class="shrink-0 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ maintenanceError }}
        </p>

        <div class="min-h-[6rem] max-h-[38vh] shrink divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in maintenanceEligibleItems"
            :key="source.item_id"
            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-3 hover:bg-gray-50 dark:hover:bg-white/5"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="maintenanceItemIds"
                type="checkbox"
                :value="source.item_id"
                :disabled="sendingToMaintenance"
                class="h-4 w-4 rounded-md border-gray-300 text-amber-600 focus:ring-amber-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span>{{ format(source.quantity) }} {{ source.unit }}</span>
              <span class="rounded-md bg-emerald-50 px-2 py-1 font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                Available
              </span>
            </span>
          </label>
          <p v-if="!maintenanceEligibleItems.length" class="p-4 text-sm text-gray-500">
            This group has no eligible available item records.
          </p>
        </div>

        <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
          {{ maintenanceItemIds.length }} item record(s) selected.
        </p>

        <div class="shrink-0 space-y-1.5">
          <label for="maintenance-issue" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Issue description <span class="text-rose-500">*</span>
          </label>
          <textarea
            id="maintenance-issue"
            v-model="maintenanceIssue"
            rows="2"
            required
            maxlength="1000"
            :disabled="sendingToMaintenance"
            placeholder="Describe the problem or repair needed"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

        <div class="shrink-0 space-y-1.5">
          <label for="maintenance-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Additional notes <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="maintenance-notes"
            v-model="maintenanceNotes"
            rows="2"
            maxlength="500"
            :disabled="sendingToMaintenance"
            placeholder="Add any relevant details"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="sendingToMaintenance"
            @click="closeMaintenanceModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="sendingToMaintenance || maintenanceItemIds.length === 0 || !maintenanceIssue.trim()"
            @click="sendSelectedToMaintenance"
          >
            {{ sendingToMaintenance ? 'Sending…' : `Send ${maintenanceItemIds.length} for Repair` }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="disposeGroup !== null"
      :title="disposeGroup ? `Dispose ${disposeGroup.item_name}` : 'Dispose inventory items'"
      subtitle="Review and select the ready-to-dispose records to permanently dispose."
      max-width="max-w-2xl"
      @close="closeDisposeModal"
    >
      <div v-if="disposeGroup" class="space-y-4">
        <p
          v-if="disposeError"
          role="alert"
          class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ disposeError }}
        </p>

        <div class="max-h-[45vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in disposeEligibleItems"
            :key="source.item_id"
            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-4 hover:bg-gray-50 dark:hover:bg-white/5"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="disposeItemIds"
                type="checkbox"
                :value="source.item_id"
                :disabled="disposing"
                class="h-4 w-4 rounded-md border-gray-300 text-rose-600 focus:ring-rose-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span class="rounded-md bg-orange-50 px-2 py-1 font-medium text-orange-700 dark:bg-orange-500/10 dark:text-orange-300">
                Ready to dispose
              </span>
            </span>
          </label>
          <p v-if="!disposeEligibleItems.length" class="p-4 text-sm text-gray-500">
            No records are currently ready for disposal in this group.
          </p>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
          {{ disposeItemIds.length }} item record(s) selected. This action cannot be undone.
        </p>

        <div class="space-y-1.5">
          <label for="dispose-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Disposal notes <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="dispose-notes"
            v-model="disposeNotes"
            rows="2"
            maxlength="500"
            :disabled="disposing"
            placeholder="Add disposal or approval reference details"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="disposing"
            @click="closeDisposeModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="disposing || disposeItemIds.length === 0"
            @click="confirmDispose"
          >
            {{ disposing ? 'Disposing…' : `Confirm Dispose ${disposeItemIds.length} Selected` }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="maintenanceActionGroup !== null"
      :title="maintenanceActionTitle"
      :subtitle="maintenanceAction === 'repair' ? 'Select the item records to complete. Rows still with their holder return to the assignee.' : 'Select the item records to mark for disposal.'"
      max-width="max-w-2xl"
      @close="closeMaintenanceActionModal"
    >
      <div v-if="maintenanceActionGroup" class="space-y-4">
        <p
          v-if="maintenanceActionError"
          role="alert"
          class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ maintenanceActionError }}
        </p>

        <div class="max-h-[45vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in actionableMaintenanceActionItems"
            :key="source.item_id"
            class="flex cursor-pointer flex-wrap items-center justify-between gap-3 p-4 hover:bg-gray-50 dark:hover:bg-white/5"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="maintenanceActionItemIds"
                type="checkbox"
                :value="source.item_id"
                :disabled="maintenanceActionBusy"
                class="h-4 w-4 rounded-md border-gray-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span v-if="Number(source.quantity ?? 0) > 0">{{ format(source.quantity) }} {{ source.unit }}</span>
              <span
                v-if="Number(source.quantity ?? 0) <= 0"
                class="rounded-md bg-indigo-50 px-2 py-1 font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"
                title="The units are still issued out — completing returns the row to the assignee"
              >
                With assignee
              </span>
              <span
                v-else
                class="rounded-md bg-amber-50 px-2 py-1 font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
              >
                Under repair
              </span>
            </span>
          </label>
          <p v-if="!actionableMaintenanceActionItems.length" class="p-4 text-sm text-gray-500">
            {{ heldMaintenanceActionCount > 0
              ? 'No actionable records here — the rest have neither stock nor holder.'
              : 'No item records are currently under repair in this group.' }}
          </p>
        </div>

        <p v-if="heldMaintenanceActionCount > 0" class="text-xs text-gray-500 dark:text-gray-400">
          {{ heldMaintenanceActionCount }} record{{ heldMaintenanceActionCount > 1 ? 's have' : ' has' }} neither stock nor holder and cannot be completed.
        </p>

        <p class="text-xs text-gray-500 dark:text-gray-400">{{ maintenanceActionItemIds.length }} item record(s) selected.</p>

        <template v-if="maintenanceAction === 'repair'">
          <div class="space-y-1.5">
            <label for="maintenance-repair-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
              Repair notes <span class="font-normal text-gray-500">(optional)</span>
            </label>
            <textarea
              id="maintenance-repair-notes"
              v-model="maintenanceRepairNotes"
              rows="2"
              maxlength="1000"
              :disabled="maintenanceActionBusy"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            />
          </div>
          <div class="space-y-1.5">
            <label for="maintenance-cost" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
              Repair cost <span class="font-normal text-gray-500">(optional)</span>
            </label>
            <input
              id="maintenance-cost"
              v-model.number="maintenanceCost"
              type="number"
              min="0"
              step="0.01"
              :disabled="maintenanceActionBusy"
              class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
            />
          </div>
        </template>
        <div v-else class="space-y-1.5">
          <label for="maintenance-disposal-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Reason for disposal <span class="text-rose-500">*</span>
          </label>
          <textarea
            id="maintenance-disposal-notes"
            v-model="maintenanceDisposalNotes"
            rows="3"
            required
            maxlength="500"
            :disabled="maintenanceActionBusy"
            placeholder="Explain why the item should be disposed"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="maintenanceActionBusy"
            @click="closeMaintenanceActionModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
            :class="maintenanceAction === 'repair' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-orange-600 hover:bg-orange-700'"
            :disabled="maintenanceActionBusy || maintenanceActionItemIds.length === 0 || (maintenanceAction === 'dispose' && !maintenanceDisposalNotes.trim())"
            @click="confirmMaintenanceAction"
          >
            {{ maintenanceActionBusy ? 'Updating…' : maintenanceAction === 'repair' ? `Confirm ${maintenanceActionItemIds.length} Repaired` : `Confirm ${maintenanceActionItemIds.length} for Disposal` }}
          </button>
        </div>
      </template>
    </Modal>

    <Modal
      :open="returnGroup !== null"
      :title="returnFromQr ? 'Confirm item return' : returnGroup ? `Return ${returnGroup.item_name}` : 'Return assigned item'"
      :subtitle="returnFromQr ? 'Confirm that you want to record this item as returned.' : 'Review the assignment details before recording the return.'"
      max-width="max-w-xl"
      @close="closeReturnModal"
    >
      <div v-if="returnGroup" class="space-y-5">
        <p
          v-if="returnFromQr && selectedReturnAssignment"
          class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200"
        >
          Are you sure you want to return {{ returnGroup.item_name }} ({{ format(selectedReturnAssignment.remaining_quantity) }} {{ selectedReturnAssignment.unit }}) assigned to {{ selectedReturnAssignment.recipient }}?
        </p>
        <p
          v-if="returnError"
          role="alert"
          class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200"
        >
          {{ returnError }}
        </p>

        <div v-if="returnAssignments.length > 1" class="space-y-1.5">
          <label for="return-assignment-select" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Assignment to return
          </label>
          <select
            id="return-assignment-select"
            v-model="returnTransactionId"
            :disabled="returning"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          >
            <option
              v-for="assignment in returnAssignments"
              :key="assignment.transaction_id"
              :value="String(assignment.transaction_id)"
            >
              {{ assignment.recipient }} · {{ assignment.remaining_quantity }} {{ assignment.unit }} remaining
            </option>
          </select>
        </div>

        <dl v-if="selectedReturnAssignment" class="grid gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-800/60 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Assigned to</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ selectedReturnAssignment.recipient }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Assignment date</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ selectedReturnAssignment.transaction_date || '—' }}</dd>
          </div>
          <div v-if="selectedReturnAssignment.expected_return_date">
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Expected return date</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ selectedReturnAssignment.expected_return_date }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Inventory record</dt>
            <dd class="mt-0.5 font-mono font-semibold text-gray-900 dark:text-white">
              {{ selectedReturnAssignment.inventory_item_no ?? `#${selectedReturnAssignment.inventory_id}` }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Serial number</dt>
            <dd class="mt-0.5 font-mono font-semibold text-gray-900 dark:text-white">{{ selectedReturnAssignment.serial_number || 'Not recorded' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Outstanding quantity</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ format(selectedReturnAssignment.remaining_quantity) }} {{ selectedReturnAssignment.unit }}
              <span class="text-xs font-normal text-gray-500">(full quantity will be returned)</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Current location</dt>
            <dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">
              {{ selectedReturnAssignment.building || 'Unspecified' }}{{ selectedReturnAssignment.room ? `, Room ${selectedReturnAssignment.room}` : '' }}
            </dd>
          </div>
        </dl>
        <div
          v-else
          role="status"
          class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
          No active returnable assignment transaction was found for this inventory group. Refresh the inventory and verify its assignment record before recording a return.
        </div>

        <div class="space-y-1.5">
          <label for="inventory-return-notes" class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
            Return notes <span class="font-normal text-gray-500">(optional)</span>
          </label>
          <textarea
            id="inventory-return-notes"
            v-model="returnNotes"
            rows="2"
            maxlength="1000"
            :disabled="returning"
            placeholder="Condition or other return details"
            class="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
          />
        </div>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="returning"
            @click="closeReturnModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="returning || !selectedReturnAssignment"
            @click="confirmReturn"
          >
            {{ returning ? 'Recording return…' : 'Confirm Return' }}
          </button>
        </div>
      </template>
    </Modal>

    <QrScannerModal
        :open="qrScannerOpen"
        title="Scan Inventory QR"
        subtitle="Point the camera at a DNHS inventory label, or enter its token."
        :resolved="Boolean(qrResult)"
        :lookup-error="qrError"
        :loading="qrLookupLoading"
        viewport-id="custodian-qr-camera-viewport"
        @close="closeQrScanner"
        @lookup="lookupQrToken"
      >
        <section class="rounded-md border border-emerald-200 bg-emerald-50/70 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10" aria-live="polite">
          <div class="mb-4 flex items-center gap-2 text-emerald-800 dark:text-emerald-300">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <h3 class="text-sm font-semibold">Item found</h3>
          </div>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Item no.</dt><dd class="font-mono font-semibold text-gray-900 dark:text-white">{{ qrResult?.inventory_item_no }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Name</dt><dd class="text-right font-medium text-gray-900 dark:text-white">{{ qrResult?.item_name }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Category</dt><dd class="text-right text-gray-700 dark:text-gray-300">{{ qrResult?.category || 'Uncategorized' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Status</dt><dd class="capitalize text-gray-700 dark:text-gray-300">{{ (qrResult?.status || '').replace(/_/g, ' ') }}</dd></div>
            <div v-if="qrResult?.serial_number" class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Serial no.</dt><dd class="font-mono text-gray-700 dark:text-gray-300">{{ qrResult.serial_number }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-500 dark:text-gray-400">Quantity</dt><dd class="font-semibold text-gray-900 dark:text-white">{{ format(qrResult?.quantity) }} {{ qrResult?.unit }}</dd></div>
            <div v-if="qrResult?.status === 'assigned' && qrResult?.assigned_to" class="flex justify-between gap-4">
              <dt class="text-gray-500 dark:text-gray-400">Assigned to</dt>
              <dd class="text-right font-medium text-gray-900 dark:text-white">{{ qrResult.assigned_to }}</dd>
            </div>
          </dl>
        </section>
        <template #footer>
          <div class="flex flex-wrap justify-end gap-2">
            <button
              v-if="qrResult?.status === 'assigned'"
              type="button"
              class="rounded-md border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-500/30 dark:text-indigo-300 dark:hover:bg-indigo-500/10"
              :disabled="!qrResult?.return_assignments?.length"
              :title="qrResult?.return_assignments?.length ? '' : 'No active return assignment was found.'"
              @click="openQrReturnModal"
            >
              Return
            </button>
            <button type="button" class="rounded-md bg-emerald-700 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-800" @click="scanAnotherQrCode">
              Scan another
            </button>
          </div>
        </template>
      </QrScannerModal>

    <!-- Print QR Codes modal: lists QR-coded items across the current workspace -->
    <Modal
      :open="qrPrintOpen"
      title="Print QR Codes"
      subtitle="Select item records that have a QR code, then print stickers."
      max-width="max-w-3xl"
      fixed-body
      @close="closeQrPrintModal"
    >
      <div class="flex min-h-0 flex-1 flex-col space-y-2">
        <!-- Search (always visible) -->
        <div class="relative w-full shrink-0">
          <svg
            class="pointer-events-none absolute left-2.5 top-2.5 h-3.5 w-3.5 text-gray-400"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
          </svg>
          <input
            v-model="qrPrintSearch"
            type="search"
            placeholder="Search item no., name, category, serial…"
            aria-label="Search QR-coded items"
            :disabled="qrPrintLoading"
            class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 pl-7 pr-7 text-[12px] text-gray-800 transition-colors focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-100"
          />
        </div>

        <!-- Loading skeleton -->
        <div v-if="qrPrintLoading" class="min-h-[12rem] max-h-[60vh] shrink overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700" aria-hidden="true">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b bg-gray-50/50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <th class="w-10 px-3 py-2.5">
                  <div class="h-4 w-4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" />
                </th>
                <th class="px-3 py-2.5"><div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5"><div class="h-3 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5"><div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5"><div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5"><div class="h-4 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></th>
                <th class="px-3 py-2.5 text-right"><div class="h-3 w-8 animate-pulse rounded-md bg-gray-100 dark:bg-white/5 ml-auto" /></th>
                <th class="px-3 py-2.5 text-center" />
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
              <tr v-for="row in 4" :key="row">
                <td class="px-3 py-2.5"><div class="h-4 w-4 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-3 w-16 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-3 w-24 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-3 w-20 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-3 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5"><div class="h-4 w-12 animate-pulse rounded-md bg-gray-100 dark:bg-white/5" /></td>
                <td class="px-3 py-2.5 text-right"><div class="h-3 w-8 animate-pulse rounded-md bg-gray-100 dark:bg-white/5 ml-auto" /></td>
                <td class="px-3 py-2.5" />
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Error state -->
        <div v-else-if="qrPrintError" class="p-4 rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400">
          {{ qrPrintError }}
          <button
            type="button"
            class="mt-2 text-xs font-semibold underline hover:text-rose-900 dark:hover:text-rose-200"
            @click="loadQrPrintItems"
          >
            Try again
          </button>
        </div>

        <!-- Real table + footer -->
        <template v-else>
          <div class="min-h-[12rem] max-h-[60vh] shrink divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700 dark:bg-gray-900/5">
            <table class="w-full text-left text-xs">
              <thead>
                <tr class="border-b bg-gray-50/50 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                  <th class="w-10 px-3 py-2.5">
                    <input
                      type="checkbox"
                      :checked="allQrPrintChecked"
                      :aria-label="filteredQrPrintItems.length ? 'Select all' : 'Select all (none available)'"
                      :disabled="!filteredQrPrintItems.length"
                      class="h-4 w-4 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                      @change="toggleAllQrPrint($event.target.checked)"
                    />
                  </th>
                  <th class="px-3 py-2.5 font-mono">Item No.</th>
                  <th class="px-3 py-2.5">Item Name</th>
                  <th class="px-3 py-2.5">Category</th>
                  <th class="px-3 py-2.5">Serial</th>
                  <th class="px-3 py-2.5 text-center">Status</th>
                  <th class="px-3 py-2.5 text-right">Qty</th>
                  <th class="px-3 py-2.5 text-center" />
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                <tr
                  v-for="item in filteredQrPrintItems"
                  :key="item.item_id"
                  class="hover:bg-gray-50/40 dark:hover:bg-white/5"
                >
                  <td class="px-3 py-2.5">
                    <input
                      v-model="qrPrintSelected"
                      :value="item.item_id"
                      type="checkbox"
                      :aria-label="`Select ${item.inventory_item_no || item.item_name}`"
                      class="h-4 w-4 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                    />
                  </td>
                  <td class="px-3 py-2.5 font-mono text-gray-900 dark:text-white truncate max-w-[8rem]" :title="item.inventory_item_no || `#${item.item_id}`">
                    {{ item.inventory_item_no || `#${item.item_id}` }}
                  </td>
                  <td class="px-3 py-2.5 text-gray-900 dark:text-white truncate">{{ item.item_name }}</td>
                  <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400 truncate">{{ item.category.category_name }}</td>
                  <td class="px-3 py-2.5 font-mono text-gray-600 dark:text-gray-400">{{ item.serial_number || '—' }}</td>
                  <td class="px-3 py-2.5 text-center capitalize">{{ item.status?.replace(/_/g, ' ') ?? '—' }}</td>
                  <td class="px-3 py-2.5 text-right text-gray-900 dark:text-white">{{ format(item.quantity) }} {{ item.unit }}</td>
                  <td class="px-3 py-2.5 text-center" />
                </tr>
                <tr v-if="!filteredQrPrintItems.length">
                  <td :colspan="8" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                    <p class="font-semibold text-gray-800 dark:text-white">No QR-coded items found</p>
                    <p class="mt-1 text-xs text-gray-400">{{ qrPrintSearch ? 'Try adjusting your search.' : 'Stock QR-eligible items first.' }}</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
      <template v-if="!qrPrintLoading && !qrPrintError" #footer>
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs text-gray-500 dark:text-gray-400">
            {{ qrPrintSelected.length }} of {{ filteredQrPrintItems.length }} selected
          </span>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-md border border-blue-200 bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-500/30 dark:hover:bg-blue-700/80"
              :disabled="qrPrintSelected.length === 0 || printingQr || qrPrintLoading"
              @click="printQrAll"
            >
              {{ printingQr ? 'Printing…' : 'Print all' }}
            </button>
            <button
              type="button"
              class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
              @click="closeQrPrintModal"
            >
              Cancel
            </button>
          </div>
        </div>
      </template>
    </Modal>

    <Modal
      :open="exportPdfOpen"
      :title="exportPdfPreviewOpen ? 'Review Inventory Report' : 'Export Inventory PDF'"
      :subtitle="exportPdfPreviewOpen ? 'Preview the report before exporting the PDF.' : 'Choose the inventory filters and ICS numbers to include.'"
      :max-width="exportPdfPreviewOpen ? 'max-w-7xl' : 'max-w-2xl'"
      :fixed-body="exportPdfPreviewOpen"
      @close="closeExportPdfModal"
    >
      <div v-if="exportPdfPreviewOpen" class="flex min-h-0 flex-1 flex-col">
        <div class="h-[65vh] min-h-[50vh] max-h-[65vh] min-w-0 flex-none overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
          <div
            v-if="exportPdfPreviewLoading"
            class="flex h-full items-center justify-center text-sm text-gray-500 dark:text-gray-400"
            role="status"
          >
            Loading report preview…
          </div>
          <div
            v-else-if="exportPdfPreviewError"
            class="flex h-full flex-col items-center justify-center gap-3 px-6 text-center"
            role="alert"
          >
            <p class="text-sm text-rose-700 dark:text-rose-300">{{ exportPdfPreviewError }}</p>
            <button
              type="button"
              class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-white dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
              @click="reviewInventoryReport"
            >
              Try again
            </button>
          </div>
          <iframe
            v-else
            ref="exportPdfPreviewFrame"
            :srcdoc="exportPdfPreviewHtml"
            class="h-full w-full"
            title="Inventory report preview"
            sandbox="allow-same-origin"
            @load="exportPdfPreviewReady = true"
          />
        </div>
      </div>
      <div v-else class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block space-y-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300">
            <span>Workspace</span>
            <select
              v-model="exportPdfWorkspace"
              class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            >
              <option v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</option>
            </select>
          </label>
          <label class="block space-y-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300">
            <span>Category</span>
            <select
              v-model="exportPdfCategory"
              class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
            >
              <option value="">All Categories</option>
              <option v-for="category in categories" :key="category.category_id" :value="String(category.category_id)">
                {{ category.category_name }}
              </option>
            </select>
          </label>
        </div>

        <section class="space-y-2" aria-label="Filter by ICS number">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-300">ICS numbers</h3>
              <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                {{ exportPdfSelectedIcs.length ? `${exportPdfSelectedIcs.length} selected` : 'No specific ICS selected — all matching ICS numbers will be included.' }}
              </p>
            </div>
            <div class="flex shrink-0 items-center gap-3 text-[11px] font-semibold">
              <button
                type="button"
                class="text-emerald-700 hover:text-emerald-800 dark:text-emerald-300"
                :disabled="!filteredExportPdfIcs.length"
                @click="toggleVisibleExportIcs(true)"
              >
                Select shown
              </button>
              <button
                type="button"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                :disabled="!exportPdfSelectedIcs.length"
                @click="exportPdfSelectedIcs = []"
              >
                Clear
              </button>
            </div>
          </div>

          <input
            v-model="exportPdfIcsSearch"
            type="search"
            placeholder="Find an exact ICS number"
            aria-label="Search ICS numbers"
            class="w-full rounded-md border border-gray-200 bg-gray-50/60 px-2.5 py-2 text-xs text-gray-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
          />

          <div
            v-if="exportPdfIcsLoading"
            class="max-h-48 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700"
            aria-label="Loading ICS numbers"
            aria-busy="true"
          >
            <div
              v-for="row in 4"
              :key="row"
              class="flex items-center gap-2.5 border-b border-gray-100 px-3 py-2 last:border-b-0 dark:border-gray-800"
            >
              <div class="h-4 w-4 animate-pulse rounded border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800" />
              <div class="h-3 w-28 animate-pulse rounded bg-gray-100 dark:bg-gray-800" />
            </div>
          </div>
          <div v-else-if="exportPdfIcsError" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300">
            {{ exportPdfIcsError }}
            <button type="button" class="ml-2 font-semibold underline" @click="loadExportPdfIcs">
              Try again
            </button>
          </div>
          <div v-else class="max-h-48 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700">
            <label
              v-for="icsNumber in filteredExportPdfIcs"
              :key="icsNumber"
              class="flex cursor-pointer items-center gap-2.5 border-b border-gray-100 px-3 py-2 text-xs text-gray-700 last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5"
            >
              <input
                v-model="exportPdfSelectedIcs"
                type="checkbox"
                :value="icsNumber"
                class="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800"
              />
              <span class="font-mono">{{ icsNumber }}</span>
            </label>
            <p v-if="!filteredExportPdfIcs.length" class="px-3 py-8 text-center text-xs text-gray-500 dark:text-gray-400">
              {{ exportPdfIcsOptions.length ? 'No ICS numbers match your search.' : 'No ICS numbers are available.' }}
            </p>
          </div>
        </section>
      </div>
      <template #footer>
        <div v-if="exportPdfPreviewOpen" class="flex items-center justify-between gap-2 pt-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            @click="backToExportFilters"
          >
            Back to Filters
          </button>
          <button
            type="button"
            :disabled="exportPdfPreviewLoading || !exportPdfPreviewReady || Boolean(exportPdfPreviewError) || exportPdfSubmitting"
            class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
            @click="exportInventoryPdf"
          >
            {{ exportPdfSubmitting ? 'Exporting…' : 'Export PDF' }}
          </button>
        </div>
        <div v-else class="flex items-center justify-end gap-2 pt-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            @click="closeExportPdfModal"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="exportPdfIcsLoading || Boolean(exportPdfIcsError) || exportPdfSubmitting || exportPdfPreviewLoading"
            class="rounded-md bg-emerald-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
            @click="reviewInventoryReport"
          >
            {{ exportPdfPreviewLoading ? 'Loading…' : 'Review Report' }}
          </button>
        </div>
      </template>
    </Modal>

    <IirupPrintModal
      :open="iirupPrintOpen"
      :categories="categories"
      :selected-ids="selection"
      :selected-count="selection.length"
      @close="iirupPrintOpen = false"
    />
    <RfqPrintModal
      :open="rfqPrintOpen"
      :selected-ids="selection"
      @close="rfqPrintOpen = false"
    />

    <Modal
      :open="itbPreviewOpen"
      title="ITB Preview"
      subtitle="Invitation to Bid for disposal of unserviceable properties"
      max-width="max-w-7xl"
      :fixed-body="true"
      :close-on-escape="true"
      @close="closeItbPreview"
    >
      <div class="itb-modal-shell relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-md border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-2 dark:border-gray-700 dark:bg-gray-900">
          <h2 class="text-sm font-bold text-gray-900 dark:text-white">Document preview</h2>
          <div class="flex gap-1 text-xs">
            <button type="button" class="rounded px-2.5 py-1 font-semibold" :class="itbPreviewMode === 'html' ? 'bg-white text-amber-700 shadow-sm dark:bg-gray-700 dark:text-amber-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'" :aria-pressed="itbPreviewMode === 'html'" @click="itbPreviewMode = 'html'">
              HTML Preview
            </button>
            <button type="button" class="rounded px-2.5 py-1 font-semibold" :class="itbPreviewMode === 'pdf' ? 'bg-white text-amber-700 shadow-sm dark:bg-gray-700 dark:text-amber-300' : 'text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-gray-700'" :aria-pressed="itbPreviewMode === 'pdf'" @click="selectItbPdfPreview">
              PDF Preview
            </button>
          </div>
        </div>

        <main class="itb-preview-main min-h-0 min-w-0 flex-1 overflow-auto p-4">
          <div v-if="itbPreviewMode === 'pdf'" class="relative h-full min-h-[24rem] overflow-hidden rounded bg-white">
            <iframe v-if="itbPdfPreviewUrl" :src="itbPdfPreviewUrl" class="h-full w-full border-0" title="ITB PDF preview" />
            <div v-else class="flex h-full items-center justify-center px-4 text-center text-sm text-gray-500" role="status">
              {{ itbPdfPreviewLoading ? 'Generating PDF preview…' : 'Select PDF Preview to generate the document.' }}
            </div>
            <div v-if="itbPdfPreviewLoading && itbPdfPreviewUrl" class="absolute right-8 top-4 rounded bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-900" role="status">
              Refreshing PDF preview…
            </div>
            <div v-if="itbPdfPreviewError" class="absolute bottom-2 left-2 right-2 rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 shadow dark:border-rose-500/30 dark:bg-gray-900 dark:text-rose-300" role="alert">
              {{ itbPdfPreviewError }}
            </div>
          </div>
          <template v-else>
          <div v-if="itbPreviewLoading" class="flex h-full min-h-[20rem] items-center justify-center text-sm text-gray-500 dark:text-gray-400" role="status">
            Preparing ITB preview…
          </div>
          <div v-else-if="itbPreviewError" class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300" role="alert">
            {{ itbPreviewError }}
          </div>
          <div v-else class="itb-preview-pages mx-auto flex flex-col items-center gap-4">
          <article
            v-for="(previewPage, pageIndex) in itbPreviewPages"
            :key="`itb-preview-page-${pageIndex}`"
            class="itb-preview-page flex flex-col bg-white p-6 text-[14px] leading-relaxed text-black shadow-sm"
            style="width: 210mm; max-width: 100%; height: 297mm; min-height: 297mm; box-sizing: border-box; font-family: Calibri, Arial, sans-serif;"
          >
            <header class="mb-4 text-center">
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

            <section class="space-y-2 text-justify leading-relaxed">
              <div v-if="pageIndex === 0" class="flex items-start gap-3">
                <span class="shrink-0">1.</span>
                <p>
                  The Department of Education (DepEd) Schools Division of Escalante City, invites all interested parties to participate in the <strong><span contenteditable="true" role="textbox" aria-label="ITB bidding description" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.bid_description = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.bid_description }}</span></strong> as indicated below:
                </p>
              </div>
              <div v-if="previewPage.type === 'items'" class="overflow-x-auto">
                <table class="w-full border-collapse border border-black text-left text-[11px]">
                  <thead>
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
                      <td class="border border-black px-2 py-1.5 text-right"><input v-model.number="item.floor_price" type="number" min="0" step="0.01" :aria-label="`Floor price for ${item.item_name}`" class="w-full border-b border-dotted border-black bg-transparent text-right outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" /></td>
                    </tr>
                    <tr v-if="previewPage.type === 'items' && itbPreviewItems.length === 0">
                      <td colspan="4" class="border border-black px-2 py-3 text-center text-gray-500">No items selected for the ITB.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <section v-if="previewPage.details.length" class="mt-4 space-y-3 text-justify leading-relaxed">
              <p v-if="previewPage.details.includes('submission')" data-itb-detail="submission">
                <strong>2.</strong> Submit your Bid form with your name, signature, company name, address, and contact numbers. Submit the duly accomplished quotation form on or before
                <input v-model="itbPreviewMetadata.submission_deadline" type="date" class="w-32 border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-center text-[11px] outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" aria-label="Submission deadline" />
                at {{ itbPreviewMetadata.school_name }}, {{ itbPreviewMetadata.school_address }}, and look for <span contenteditable="true" role="textbox" aria-label="Secretariat name" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.secretariat_name = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.secretariat_name }}</span>.
              </p>
              <div v-if="previewPage.details.includes('requirements')" data-itb-detail="requirements" class="flex items-start gap-3">
                <strong class="shrink-0">3.</strong>
                <div>
                  <p>Attach to your Bid either the following:</p>
                  <ul class="ml-6 list-disc">
                    <li><span contenteditable="true" role="textbox" aria-label="Corporation registration requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.corporation_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.corporation_requirement }}</span></li>
                    <li><span contenteditable="true" role="textbox" aria-label="Sole proprietorship registration requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.proprietorship_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.proprietorship_requirement }}</span></li>
                    <li><span contenteditable="true" role="textbox" aria-label="Business permit requirement" class="cursor-text border-b border-dotted border-black outline-none focus:border-solid focus:border-amber-600 focus:bg-amber-50" @input="itbPreviewMetadata.business_permit_requirement = $event.currentTarget.textContent ?? ''">{{ itbPreviewMetadata.business_permit_requirement }}</span></li>
                    <li v-for="(requirement, index) in itbPreviewMetadata.additional_requirements" :key="`itb-requirement-preview-${index}`"><input v-model="itbPreviewMetadata.additional_requirements[index]" type="text" :aria-label="`Additional requirement ${index + 1}`" class="inline-block w-[calc(100%-2rem)] border-0 border-b border-dotted border-black bg-transparent p-0 align-middle outline-none focus:border-solid focus:border-amber-600" /><button type="button" class="itb-preview-controls float-right inline-flex h-5 w-5 items-center justify-center rounded-full border-0 text-gray-500 hover:bg-rose-50 hover:text-rose-600 focus:border-0 focus:outline-none focus:ring-0" :aria-label="`Remove additional requirement ${index + 1}`" title="Remove requirement" @click="removeItbRequirement(index)"><svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z" /></svg></button></li>
                  </ul>
                  <button type="button" class="itb-preview-controls mt-1 inline-flex items-center gap-1 rounded border-0 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50 focus:border-0 focus:outline-none focus:ring-0" aria-label="Add requirement" title="Add requirement" @click="addItbRequirement">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z" /></svg>
                    Add requirement
                  </button>
                </div>
              </div>
              <p v-if="previewPage.details.includes('disqualification')" data-itb-detail="disqualification"><strong>4.</strong> Unsigned Bid and Bid lower than the floor price shall automatically be disqualified.</p>
              <p v-if="previewPage.details.includes('forms')" data-itb-detail="forms"><strong>5.</strong> A complete set of Bid Form may be acquired by interested bidder from <input v-model="itbPreviewMetadata.form_acquisition_from" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid form availability start date" /> to <input v-model="itbPreviewMetadata.form_acquisition_to" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Bid form availability end date" /> at School Head office, {{ itbPreviewMetadata.school_name }}.</p>
              <div v-if="previewPage.details.includes('contacts')" data-itb-detail="contacts" class="flex items-start gap-3">
                <strong class="shrink-0">6.</strong>
                <div class="min-w-0 flex-1">
                  <p>For further information, please refer to:</p>
                  <p class="mt-2">
                    <input v-model="itbPreviewMetadata.secretariat_name" type="text" size="1" aria-label="Secretariat name" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 font-semibold outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.secretariat_name.length, 1)}ch` }" />
                    <input v-model="itbPreviewMetadata.secretariat_role" type="text" size="1" aria-label="Secretariat role" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.secretariat_role.length, 1)}ch` }" />
                    <input v-model="itbPreviewMetadata.secretariat_role_detail" type="text" size="1" aria-label="Secretariat role detail" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.secretariat_role_detail.length, 1)}ch` }" />
                    <input v-model="itbPreviewMetadata.contact_numbers" type="text" size="1" aria-label="Secretariat contact numbers" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.contact_numbers.length, 1)}ch` }" />
                  </p>
                </div>
              </div>
              <p v-if="previewPage.details.includes('inspection')" data-itb-detail="inspection"><strong>7.</strong> Inspection of Unserviceable Properties are available for inspection on <input v-model="itbPreviewMetadata.inspection_day" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Inspection date" /> @ <input v-model="itbPreviewMetadata.inspection_time" type="text" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Inspection time" />, {{ itbPreviewMetadata.school_name }}.</p>
              <div v-if="previewPage.details.includes('auction')" data-itb-detail="auction">
                <p><strong>8.</strong> Schedule of Public Auction:</p>
                <p class="pl-6">The public auction shall be conducted on an “as is, where is” basis on <input v-model="itbPreviewMetadata.auction_date" type="date" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Auction date" /> at <input v-model="itbPreviewMetadata.auction_time" type="text" class="border-0 border-b border-dotted border-black bg-transparent px-1 py-0.5 text-[11px] outline-none focus:border-solid focus:border-amber-600" aria-label="Auction time" />, {{ itbPreviewMetadata.school_name }}.</p>
              </div>
              <div v-if="previewPage.details.includes('bidSubmission')" data-itb-detail="bidSubmission">
                <p><strong>9.</strong> Submission of Bid:</p>
                <p class="pl-6">The Bid must be submitted in three <input v-model="itbPreviewMetadata.bid_submission_copies" type="text" size="1" aria-label="Bid submission copy details" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px] outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.bid_submission_copies.length, 1)}ch` }" /> using the prescribed Bid Form.</p>
              </div>
              <p v-if="previewPage.details.includes('bidDeadline')" data-itb-detail="bidDeadline" class="text-left"><strong>10.</strong> Deadline of submission of Bid is on {{ itbPreviewMetadata.submission_deadline }} {{ itbPreviewMetadata.submission_time }}. Bid submitted beyond said deadline shall not be accepted.</p>
              <p v-if="previewPage.details.includes('openingBids')" data-itb-detail="openingBids" class="text-left"><strong>11.</strong> Opening of Bids at {{ itbPreviewMetadata.bid_opening_date }} {{ itbPreviewMetadata.bid_opening_time }} in the presence of BAC and Division.</p>
              <p v-if="previewPage.details.includes('disposalCommittee')" data-itb-detail="disposalCommittee"><strong>12.</strong> Disposal Committee.</p>
              <div v-if="previewPage.details.includes('awardingBids')" data-itb-detail="awardingBids">
                <p><strong>13.</strong> Awarding of Bids:</p>
                <p class="pl-6">Award shall be made to the highest bidder.</p>
              </div>
              <div v-if="previewPage.details.includes('pulloutItems')" data-itb-detail="pulloutItems">
                <p><strong>14.</strong> Pull-out/Taking of Awarded Items:</p>
                <p class="pl-6">The winning bidder shall be given <input v-model="itbPreviewMetadata.pullout_period" type="text" size="1" aria-label="Pull-out period" class="border-0 border-b border-dotted border-black bg-transparent p-0 text-[11px] outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.pullout_period.length, 1)}ch` }" /> from receipt of payment to pull out from the Old Division Office as stated in the ITB. The unserviceable items awarded subject to the supervision of Division Supply Officer, Division Accountant, Representative from the Division Inventory and Disposal Committee, witnessing and approval by the Schools Division Superintendent and COA official during the actual disposal.</p>
              </div>
              <table v-if="previewPage.details.includes('signatories')" data-itb-detail="signatories" style="width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 56px; font-size: 11pt; line-height: 1.35; text-align: left;">
                <tbody>
                  <tr v-for="(row, rowIndex) in [[0, 1], [2, 3], [4, 5]]" :key="`visible-signatory-row-${rowIndex}`" :style="rowIndex === 0 ? 'height: 120px;' : rowIndex === 1 ? 'height: 90px;' : ''">
                    <td v-for="index in row" :key="`visible-signatory-${index}`" style="width: 50%; padding: 0; vertical-align: top;">
                      <input v-model="itbPreviewMetadata.signatories[index].name" type="text" size="1" :aria-label="`Signatory ${index + 1} name`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 font-semibold outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${Math.max(itbPreviewMetadata.signatories[index].name.length * 0.72 + 2, 1)}em` }" />
                      <input v-model="itbPreviewMetadata.signatories[index].title" type="text" size="1" :aria-label="`Signatory ${index + 1} title`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${itbPreviewMetadata.signatories[index].title.length + 1}ch` }" />
                      <input v-if="itbPreviewMetadata.signatories[index].role" v-model="itbPreviewMetadata.signatories[index].role" type="text" size="1" :aria-label="`Signatory ${index + 1} role`" class="block max-w-full border-0 border-b border-dotted border-black bg-transparent p-0 outline-none focus:border-solid focus:border-amber-600" :style="{ width: `${itbPreviewMetadata.signatories[index].role.length + 1}ch` }" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </section>

            <footer class="mt-auto grid grid-cols-[45%_55%] items-center border-t border-gray-500 py-2 text-[11px] leading-tight">
              <div class="flex items-center gap-2">
                <img
                  :src="'/images/footer/image.png'"
                  alt="Department of Education and Bagong Pilipinas logos"
                  class="block h-auto w-[150px] max-w-[calc(100%-50px)]"
                />
                <img
                  :src="'/images/footer/escalante_city_education_seal.svg'"
                  alt="Escalante City Division Department of Education seal"
                  class="block h-auto w-[42px] shrink-0"
                />
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
          </template>
        </main>

      </div>
      <template #footer>
        <div class="itb-preview-controls flex justify-end gap-2">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            @click="closeItbPreview"
          >
            Back to selection
          </button>
          <button
            v-if="itbPreviewMode === 'pdf'"
            type="button"
            class="rounded-md border border-amber-200 bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-500/30"
            :disabled="!itbPdfPreviewUrl || !itbPdfPreviewCurrent || itbPdfPreviewLoading"
            @click="downloadItbPdf"
          >
            Download PDF
          </button>
          <button
            v-if="itbPreviewMode === 'html'"
            type="button"
            class="rounded-md border border-amber-200 bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-700 dark:border-amber-500/30"
            @click="printItbDocument"
          >
            Print ITB
          </button>
        </div>
      </template>
    </Modal>

    <!-- Modals -->
    <StockInModal :open="stockInOpen" :categories="categories" @close="stockInOpen = false" @saved="onStockInSaved" @categories-updated="categories = $event" />
    <EditInventoryModal :open="editItemId !== null" :item-id="editItemId" :categories="categories" @close="editItemId = null" @saved="onEditSaved" />

    <Modal
      :open="deleteGroup !== null"
      :title="deleteGroup ? `Delete ${deleteGroup.item_name} items` : 'Delete inventory items'"
      :subtitle="deleteGroup ? 'Select available records to delete. Items with request or assignment history must be disposed to preserve their records.' : ''"
      max-width="max-w-2xl"
      @close="closeDeleteModal"
    >
      <div v-if="deleteGroup" class="space-y-4">
        <p v-if="deleteError" role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-200">
          {{ deleteError }}
        </p>

        <div class="max-h-[55vh] divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200 dark:divide-white/5 dark:border-gray-700">
          <label
            v-for="source in deleteGroup.sourceItems ?? []"
            :key="source.item_id"
            class="flex flex-wrap items-center justify-between gap-3 p-4"
            :class="source.status === 'available' ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5' : 'opacity-60'"
          >
            <span class="flex min-w-0 items-center gap-3">
              <input
                v-model="deleteItemIds"
                type="checkbox"
                :value="source.item_id"
                :disabled="source.status !== 'available' || deleting"
                class="h-4 w-4 rounded-md border-gray-300 text-rose-600 focus:ring-rose-500 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-800"
              />
              <span class="min-w-0">
                <span class="block font-mono text-sm font-semibold text-gray-900 dark:text-white">
                  {{ source.inventory_item_no ?? `#${source.item_id}` }}
                </span>
                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                  {{ source.serial_number ? `Serial: ${source.serial_number}` : 'No serial number' }}
                </span>
              </span>
            </span>
            <span class="flex items-center gap-2 text-xs">
              <span>{{ format(source.quantity) }} {{ source.unit }}</span>
              <span class="rounded-md bg-gray-100 px-2 py-1 capitalize text-gray-600 dark:bg-white/10 dark:text-gray-300">
                {{ (source.status ?? 'unknown').replace(/_/g, ' ') }}
              </span>
            </span>
          </label>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
          {{ deleteItemIds.length }} record(s) selected. Only available inventory can be deleted.
        </p>

      </div>
      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
            :disabled="deleting"
            @click="closeDeleteModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="deleting || deleteItemIds.length === 0"
            @click="doDelete"
          >
            {{ deleting ? 'Deleting…' : `Delete ${deleteItemIds.length} selected` }}
          </button>
        </div>
      </template>
    </Modal>

    <ConfirmDialog
      :open="confirmBulk"
      title="Delete selected items?"
      :message="`${selection.length} selected inventory record(s) will be deleted. Items with request or assignment history must be disposed to preserve their records.`"
      :busy="deleting"
      confirm-label="Delete"
      @cancel="confirmBulk = false"
      @confirm="doDelete"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import QRCode from 'qrcode';
import { autoUpdate, computePosition, flip, offset, shift } from '@floating-ui/dom';
import { Armchair, BookOpen, Camera, Dumbbell, FileStack, FlaskConical, HardDrive, Monitor, Package, Printer, Projector, Wrench } from 'lucide';
import api from '../../lib/axios';
import { forgetPageCache, loadCachedPage } from '../../lib/pageCache';
import { useToastStore } from '../../stores/toast';
import InventoryTableSkeleton from '../../components/ui/skeletons/InventoryTableSkeleton.vue';
import LucideIcon from '../../components/ui/data-display/LucideIcon.vue';
import Pagination from '../../components/ui/data-display/Pagination.vue';
import StatusBadge from '../../components/ui/data-display/StatusBadge.vue';
import ConfirmDialog from '../../components/ui/dialogs/ConfirmDialog.vue';
import Modal from '../../components/ui/dialogs/Modal.vue';
import QrScannerModal from '../../components/ui/qr/QrScannerModal.vue';
import StockInModal from './StockInModal.vue';
import InventoryItemLogDrawer from './InventoryItemLogDrawer.vue';
import EditInventoryModal from './EditInventoryModal.vue';
import IirupPrintModal from './components/IirupPrintModal.vue';
import RfqPrintModal from './components/RfqPrintModal.vue';

const STATUSES = ['available', 'assigned', 'under_repair', 'ready_to_dispose', 'disposed'];
const EXPORT_PDF_SIGNATURE_FIELDS = [
  'received_from_name',
  'received_from_position',
  'received_from_date',
  'received_by_name',
  'received_by_role',
  'received_by_date',
];
const ITEM_ICONS = [
  { terms: ['bond paper', 'coupon bond', 'copy paper', 'ream', 'paper', 'envelope', 'folder', 'notepad', 'notebook', 'index card', 'cartolina', 'manila paper', 'parchment', 'sticky note'], icon: FileStack },
  { terms: ['document camera', 'camera'], icon: Camera },
  { terms: ['printer', 'epson'], icon: Printer },
  { terms: ['external hard drive', 'hard drive', 'storage drive'], icon: HardDrive },
  { terms: ['projector'], icon: Projector },
  { terms: ['laptop', 'computer', 'monitor', 'desktop'], icon: Monitor },
  { terms: ['book', 'workbook'], icon: BookOpen },
  { terms: ['chair', 'desk', 'table'], icon: Armchair },
];
const CATEGORY_ICONS = [
  { terms: ['consumable', 'office suppl'], icon: FileStack },
  { terms: ['furniture'], icon: Armchair },
  { terms: ['ict', 'information technology'], icon: Monitor },
  { terms: ['office equipment'], icon: Printer },
  { terms: ['laboratory'], icon: FlaskConical },
  { terms: ['learning resource'], icon: BookOpen },
  { terms: ['sports'], icon: Dumbbell },
  { terms: ['tools', 'maintenance equipment'], icon: Wrench },
];
const STATUS_LABELS = {
  all: 'All Inventory',
  available: 'Available',
  assigned: 'Assigned',
  under_repair: 'Under Repair',
  ready_to_dispose: 'Ready to Dispose',
  disposed: 'Disposed',
};

const workspace = ref('all');
const workspaceMenuOpen = ref(false);
const page = ref(1);
const search = ref('');
const categoryFilter = ref('');
const loading = ref(true);
const error = ref('');
const inventoryCachePage = 'custodian-inventory';

function inventoryCacheParams() {
  return { workspace: workspace.value, page: page.value };
}

function applyInventoryPayload(data) {
  metrics.value = data.inventoryMetrics ?? {};
  counts.value = data.inventoryStatusCounts ?? {};
  categories.value = data.categories ?? [];
  allPage.value = data.allInventoryPage ?? { data: [] };
  statusPages.value = data.inventoryPages ?? {};
}
const deleting = ref(false);
const stockInOpen = ref(false);
const logsOpen = ref(false);
const qrScannerOpen = ref(false);
const qrLookupLoading = ref(false);
const qrError = ref('');
const qrResult = ref(null);
const editItemId = ref(null);
const confirmBulk = ref(false);
const selection = ref([]);
const recordGroup = ref(null);
const selectedPrintItemIds = ref([]);
// "Print QR Codes" header modal state: lists QR-coded items across the current workspace.
const qrPrintOpen = ref(false);
const exportPdfOpen = ref(false);
const exportPdfWorkspace = ref('all');
const exportPdfCategory = ref('');
const exportPdfIcsOptions = ref([]);
const exportPdfSelectedIcs = ref([]);
const exportPdfIcsSearch = ref('');
const exportPdfIcsLoading = ref(false);
const exportPdfIcsError = ref('');
const exportPdfSubmitting = ref(false);
const exportPdfPreviewOpen = ref(false);
const exportPdfPreviewLoading = ref(false);
const exportPdfPreviewError = ref('');
const exportPdfPreviewHtml = ref('');
const exportPdfPreviewFrame = ref(null);
const exportPdfPreviewReady = ref(false);
const exportPdfSignatureValues = ref({});
let exportPdfPreviewRequestId = 0;
const iirupPrintOpen = ref(false);
const rfqPrintOpen = ref(false);
const itbSelectionModalOpen = ref(false);
const itbSelectionDrawerOpen = ref(false);
const itbPreviewOpen = ref(false);
const itbItemsLoading = ref(false);
const itbItemsLoaded = ref(false);
const itbItemsError = ref('');
const itbPreviewLoading = ref(false);
const itbPreviewError = ref('');
const itbPreviewMode = ref('html');
const itbPdfPreviewUrl = ref('');
const itbPdfPreviewLoading = ref(false);
const itbPdfPreviewError = ref('');
const itbPdfPreviewCurrent = ref(false);
let itbPdfPreviewRequestId = 0;
let itbPdfPreviewRefreshTimer = null;
const itbEligibleByCategory = ref([]);
const itbSelectedIds = ref([]);
const itbConfirmedSelectedIds = ref([]);
const itbExpandedCategories = ref([]);
const itbSelectionStep = ref('select');
const qrPrintItems = ref([]);
const qrPrintSelected = ref([]);
const qrPrintSearch = ref('');
const qrPrintLoading = ref(false);
const qrPrintError = ref('');
const reportDecisionId = ref(null);
const incidentQueueOpen = ref(false);
const incidentQueue = ref([]);
const incidentQueueLoading = ref(false);
const incidentQueueError = ref('');
const incidentDetailsReport = ref(null);
const incidentDetailsTrigger = ref(null);
const incidentDetailsPopover = ref(null);
const incidentDetailsCloseButton = ref(null);
const incidentDetailsStyle = ref({ left: '0px', top: '0px', visibility: 'hidden' });
const incidentActionId = ref(null);
const incidentInspectionReport = ref(null);
const incidentInspectionFindings = ref('');
const incidentInspectionRepairability = ref('pending');
const incidentInformationReport = ref(null);
const incidentInformationReason = ref('');
const incidentHandoverReport = ref(null);
const incidentHandoverDetails = ref('');
const investigationActionReport = ref(null);
const investigationActionDetails = ref('');
const investigationEvidenceFiles = ref([]);
const investigationResolutionReport = ref(null);
const investigationResolutionOutcome = ref('recovered');
const investigationResolutionDetails = ref('');
const maintenanceGroup = ref(null);
const maintenanceItemIds = ref([]);
const maintenanceIssue = ref('');
const maintenanceNotes = ref('');
const maintenanceError = ref('');
const sendingToMaintenance = ref(false);
const maintenanceActionGroup = ref(null);
const maintenanceAction = ref('repair');
const maintenanceActionItemIds = ref([]);
const maintenanceRepairNotes = ref('');
const maintenanceCost = ref(null);
const maintenanceDisposalNotes = ref('');
const maintenanceActionError = ref('');
const maintenanceActionBusy = ref(false);
const disposeGroup = ref(null);
const disposeItemIds = ref([]);
const disposeNotes = ref('');
const disposeError = ref('');
const disposing = ref(false);
const returnGroup = ref(null);
const returnFromQr = ref(false);
const returnTransactionId = ref('');
const returnNotes = ref('');
const returnError = ref('');
const returning = ref(false);
const deleteGroup = ref(null);
const deleteItemIds = ref([]);
const deleteError = ref('');

const metrics = ref({});
const counts = ref({});
const categories = ref([]);
const allPage = ref({ data: [] });
const statusPages = ref({});

const itbCategoryGroups = computed(() => {
  return itbEligibleByCategory.value
    .filter((group) => group.items.length > 0)
    .sort((a, b) => a.category_name.localeCompare(b.category_name));
});
const itbEligibleItemCount = computed(() => itbEligibleByCategory.value
  .reduce((count, group) => count + group.items.length, 0));
const itbSelectedItems = computed(() => itbEligibleByCategory.value
  .flatMap((group) => group.items.filter((item) => itbSelectedIds.value.includes(item.item_id))));
const itbPreviewItems = computed(() => itbEligibleByCategory.value
  .flatMap((group) => group.items.filter((item) => itbSelectedIds.value.includes(item.item_id))));
const itbMeasureContainer = ref(null);
const itbPreviewPages = ref([{ type: 'items', items: [], details: [], startIndex: 0 }]);
let itbPaginationRequest = 0;
const itbPreviewMetadata = ref({
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
const itbDetailKeys = ['submission', 'requirements', 'disqualification', 'forms', 'contacts', 'inspection', 'auction', 'bidSubmission', 'bidDeadline', 'openingBids', 'disposalCommittee', 'awardingBids', 'pulloutItems', 'signatories'];

function itbElementHeight(element, includeMargins = false) {
  if (!element) return 0;
  const rect = element.getBoundingClientRect();
  if (!includeMargins) return rect.height;
  const style = window.getComputedStyle(element);
  return rect.height + (Number.parseFloat(style.marginTop) || 0) + (Number.parseFloat(style.marginBottom) || 0);
}

async function refreshItbPreviewPages() {
  const request = ++itbPaginationRequest;
  await nextTick();
  const container = itbMeasureContainer.value;
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
  const detailElements = new Map(itbDetailKeys.map((key) => [key, container.querySelector(`[data-itb-detail="${key}"]`)]));
  const padding = window.getComputedStyle(page);
  const contentHeight = page.clientHeight
    - (Number.parseFloat(padding.paddingTop) || 0)
    - (Number.parseFloat(padding.paddingBottom) || 0);
  const footerHeight = itbElementHeight(footer) + 8;
  const tableStyle = window.getComputedStyle(table);
  const tableHeaderHeight = itbElementHeight(tableHead)
    + (Number.parseFloat(tableStyle.marginTop) || 0)
    + (Number.parseFloat(tableStyle.marginBottom) || 0);
  const items = itbPreviewItems.value;
  const itemHeights = rowElements.map((row) => itbElementHeight(row));
  const detailHeights = new Map(itbDetailKeys.map((key) => [
    key,
    itbElementHeight(detailElements.get(key), true),
  ]));
  const detailGap = 12;
  const detailSectionTop = 16;
  const pages = [];
  let itemIndex = 0;
  let detailIndex = 0;

  const addPage = (type, header, startIndex) => {
    const available = contentHeight - itbElementHeight(header, true) - footerHeight;
    const result = { type, items: [], details: [], startIndex, remaining: available };
    pages.push(result);
    return result;
  };
  const consumeDetails = (currentPage) => {
    while (detailIndex < itbDetailKeys.length) {
      const key = itbDetailKeys[detailIndex];
      const height = detailHeights.get(key) || 0;
      const spacing = currentPage.details.length === 0
        ? detailSectionTop
        : detailGap;
      if (height + spacing > currentPage.remaining && (currentPage.items.length || currentPage.details.length || pages.length > 1)) {
        currentPage = addPage('details', continuationHeader, itemIndex);
      }
      currentPage.remaining -= (currentPage.details.length === 0 ? detailSectionTop : detailGap) + height;
      currentPage.details.push(key);
      detailIndex += 1;
    }
  };

  let currentPage = addPage('items', firstHeader, 0);
  currentPage.remaining -= (items.length > 0 ? itbElementHeight(intro, true) : 0) + tableHeaderHeight;
  if (items.length === 0) {
    currentPage.remaining -= itbElementHeight(emptyRow);
  }
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

  if (request === itbPaginationRequest) {
    itbPreviewPages.value = pages;
  }
}
watch([itbPreviewItems, itbPreviewMetadata], refreshItbPreviewPages, { deep: true, flush: 'post', immediate: true });
watch([
  itbPreviewMetadata,
  () => itbEligibleByCategory.value.flatMap((group) => group.items.map((item) => item.floor_price)),
], () => {
  if (!itbSelectionModalOpen.value || itbPreviewMode.value !== 'pdf') return;

  itbPdfPreviewRequestId += 1;
  itbPdfPreviewLoading.value = false;
  itbPdfPreviewCurrent.value = false;
  clearTimeout(itbPdfPreviewRefreshTimer);
  itbPdfPreviewRefreshTimer = setTimeout(() => {
    itbPdfPreviewRefreshTimer = null;
    generateItbPdfPreview();
  }, 500);
}, { deep: true });
onUnmounted(clearItbPdfPreview);

const isIncidentWorkspace = computed(() => ['incident_reports', 'investigations'].includes(workspace.value));
const investigationQueue = computed(() => incidentQueue.value.filter((report) => report.status === 'under_investigation'));
const reviewIncidentQueue = computed(() => incidentQueue.value.filter((report) => report.status !== 'under_investigation'));
const displayedIncidentQueue = computed(() => (
  workspace.value === 'investigations' ? investigationQueue.value : reviewIncidentQueue.value
));

const tabs = computed(() => [
  {
    key: 'all',
    label: STATUS_LABELS.all,
    // The server total is de-duplicated because partially issued inventory can
    // appear in both the Available and Assigned workspaces.
    count: Number(metrics.value?.total ?? 0),
  },
  ...STATUSES.map((status) => ({ key: status, label: STATUS_LABELS[status], count: Number(counts.value?.[status] ?? 0) })),
  {
    key: 'incident_reports',
    label: 'Incident Reports',
    count: reviewIncidentQueue.value.length,
  },
  {
    key: 'investigations',
    label: 'Investigations',
    count: investigationQueue.value.length,
  },
]);

const activeWorkspaceTab = computed(() => tabs.value.find((tab) => tab.key === workspace.value) ?? tabs.value[0]);

const paginator = computed(() =>
  workspace.value === 'all' ? allPage.value ?? { data: [] } : statusPages.value?.[workspace.value] ?? { data: [] },
);

const rows = computed(() => paginator.value.data ?? []);
const maintenanceEligibleItems = computed(() => (maintenanceGroup.value?.sourceItems ?? [])
  .filter((source) => source.status === 'available' && isMaintenanceEligible(source)));
const maintenanceActionItems = computed(() => (maintenanceActionGroup.value?.sourceItems ?? [])
  .filter((source) => source.status === 'under_repair'));
// Zero-stock rows are still issued out, so they cannot be completed here.
// They stay hidden from the picker with a count note instead of a disabled
// row, which read as broken rather than informative.
const hasStockOrHolder = (source) => Number(source.quantity ?? 0) > 0 || Number(source.assigned_quantity ?? 0) > 0;
const actionableMaintenanceActionItems = computed(() =>
  maintenanceActionItems.value.filter(hasStockOrHolder));
const heldMaintenanceActionCount = computed(() =>
  maintenanceActionItems.value.filter((source) => !hasStockOrHolder(source)).length);
const disposeEligibleItems = computed(() => (disposeGroup.value?.sourceItems ?? [])
  .filter((source) => source.status === 'ready_to_dispose'));
const maintenanceActionTitle = computed(() => maintenanceAction.value === 'repair'
  ? `Mark ${maintenanceActionGroup.value?.item_name ?? 'items'} as repaired`
  : `Mark ${maintenanceActionGroup.value?.item_name ?? 'items'} ready to dispose`);
const returnAssignments = computed(() => (returnGroup.value?.sourceItems ?? [])
  .flatMap((source) => source.receive_return_assignments ?? []));
const selectedReturnAssignment = computed(() =>
  returnAssignments.value.find((assignment) => String(assignment.transaction_id) === returnTransactionId.value) ?? null,
);

const filteredRows = computed(() => {
  const query = search.value.toLowerCase().trim();
  return rows.value.filter((row) => {
    if (categoryFilter.value && String(row.category_id) !== String(categoryFilter.value)) {
      return false;
    }
    if (!query) {
      return true;
    }
    return [row.item_name, row.category?.category_name, row.ics_no, row.description, rowAssignees(row)]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));
  });
});

const filteredQrPrintItems = computed(() => {
  const query = qrPrintSearch.value.toLowerCase().trim();
  if (!query) {
    return qrPrintItems.value;
  }
  return qrPrintItems.value.filter((item) =>
    [item.inventory_item_no, item.item_name, item.category?.category_name, item.serial_number]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query)),
  );
});

const filteredExportPdfIcs = computed(() => {
  const query = exportPdfIcsSearch.value.trim().toLowerCase();
  if (!query) {
    return exportPdfIcsOptions.value;
  }
  return exportPdfIcsOptions.value.filter((icsNumber) => icsNumber.toLowerCase().includes(query));
});

const exportPdfModalHref = computed(() => {
  const params = new URLSearchParams({
    workspace: exportPdfWorkspace.value,
    category_id: exportPdfCategory.value,
  });
  exportPdfSelectedIcs.value.forEach((icsNumber) => params.append('ics_numbers[]', icsNumber));

  return `/property-custodian/inventory/export-pdf?${params.toString()}`;
});

const allChecked = computed({
  get: () => filteredRows.value.length > 0 && filteredRows.value.every((row) => isGroupSelected(row)),
  set: (checked) => {
    const ids = new Set(selection.value);
    filteredRows.value.forEach((row) => {
      groupSourceIds(row).forEach((id) => {
        if (checked) {
          ids.add(id);
        } else {
          ids.delete(id);
        }
      });
    });
    selection.value = [...ids];
  },
});

function tabBadgeClass(key, isActive) {
  if (isActive) {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300';
  }
  switch (key) {
    case 'available':
      return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300';
    case 'assigned':
      return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300';
    case 'under_repair':
      return 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
    case 'ready_to_dispose':
      return 'bg-orange-50 text-orange-700 dark:bg-orange-950/40 dark:text-orange-300';
    case 'disposed':
      return 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
    default:
      return 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
  }
}

// Must mirror the server-side group key (PropertyCustodianController::inventory).
// Groups collapse by (name, category, unit, status, ics_no): units sharing one
// ICS slip render as a single `N pieces` row, per-unit serials stay in sourceItems.
function groupKey(row) {
  return `${row.item_name}|${row.category_id}|${row.unit}|${row.status}|${row.ics_no ?? ''}`;
}

// A collapsed row stands for every source record in it, so list-level selection
// must carry all of its item_ids — otherwise bulk delete would only delete the
// MIN id and silently leave its slip-mates behind.
function groupSourceIds(row) {
  const sources = row?.sourceItems ?? [];
  if (sources.length) {
    return sources.map((source) => source.item_id).filter((id) => id !== undefined && id !== null);
  }
  return [row?.source_item_id].filter((id) => id !== undefined && id !== null);
}

function isGroupSelected(row) {
  const ids = groupSourceIds(row);
  return ids.length > 0 && ids.every((id) => selection.value.includes(id));
}

function toggleGroupSelection(row, checked) {
  const ids = new Set(selection.value);
  groupSourceIds(row).forEach((id) => {
    if (checked) {
      ids.add(id);
    } else {
      ids.delete(id);
    }
  });
  selection.value = [...ids];
}

// Structured identifiers for a collapsed row. Returning an object instead of a
// pre-joined string lets each chip be styled on its own; the template evaluates
// this once per row via a single-element v-for.
function groupMeta(row) {
  const sourceItems = row?.sourceItems ?? [];
  const source = sourceItems[0] ?? null;
  const count = Number(row?.source_count ?? sourceItems.length ?? 0);

  return {
    key: `${row?.item_name ?? ''}|${row?.ics_no ?? ''}|${row?.status ?? ''}|${count}`,
    count,
    // Only show a single unit's identifiers; a collapsed row spans many units and
    // the per-unit values live in the Records modal.
    invNo: count > 1 ? '' : (source?.inventory_item_no ?? row?.inventory_item_no ?? ''),
    serial: count > 1 ? '' : (source?.serial_number ?? ''),
  };
}

function groupSubtitle(row) {
  const { count, invNo, serial } = groupMeta(row);

  if (count > 1) {
    return `${count} unit records`;
  }

  if (invNo && serial) {
    return `${invNo} · SN: ${serial}`;
  }

  return invNo || (serial ? `SN: ${serial}` : '');
}

function hasQrCodes(row) {
  return (row.sourceItems ?? []).some((source) => Boolean(source.qr_code));
}

const qrSources = computed(() => (recordGroup.value?.sourceItems ?? [])
  .filter((source) => Boolean(source.qr_code)));

const allPrintChecked = computed(() =>
  qrSources.value.length > 0 && selectedPrintItemIds.value.length === qrSources.value.length,
);

function toggleAllPrint(checked) {
  selectedPrintItemIds.value = checked ? qrSources.value.map((source) => source.item_id) : [];
}

const allQrPrintChecked = computed(() =>
  filteredQrPrintItems.value.length > 0 && qrPrintSelected.value.length === filteredQrPrintItems.value.length,
);

function toggleAllQrPrint(checked) {
  qrPrintSelected.value = checked ? filteredQrPrintItems.value.map((item) => item.item_id) : [];
}

const printingQr = ref(false);

function escapeStickerHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

async function printStickers(itemsToPrint) {
  if (!itemsToPrint.length || printingQr.value) return;
  printingQr.value = true;
  let frame = null;
  try {
    let stickersHtml = '';
    for (const source of itemsToPrint) {
      let qrDataUrl = '';
      try {
        qrDataUrl = await QRCode.toDataURL(source.qr_code, {
          width: 320,
          margin: 0,
          color: { dark: '#1d4ed8', light: '#ffffff' },
        });
      } catch {}
      const itemName = escapeStickerHtml(source.item_name ?? recordGroup.value?.item_name ?? 'INVENTORY ITEM');
      const invNo = escapeStickerHtml(source.inventory_item_no ?? `#${source.item_id}`);
      const serialNo = escapeStickerHtml(source.serial_number || 'Not recorded');
      const category = escapeStickerHtml(source.category?.category_name ?? recordGroup.value?.category?.category_name ?? 'Uncategorized');
      const qrText = escapeStickerHtml(source.qr_code ?? '');
      const qrImgHtml = qrDataUrl
        ? `<img src="${qrDataUrl}" alt="QR Code" style="width:115px;height:115px;display:block;" />`
        : '<div style="width:115px;height:115px;display:flex;align-items:center;justify-content:center;border:1px solid #e5e7eb;font-size:10px;color:#9ca3af;">NO QR</div>';

      stickersHtml += '<div style="display:block;width:9cm;margin:0;border:2px solid #1d4ed8;background:#fff;padding:14px;page-break-inside:avoid;break-inside:avoid;">'
        + '<div style="border-bottom:1.5px solid #dbeafe;padding-bottom:6px;text-align:center;">'
        + '<div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#1d4ed8;">Dian-ay National High School (NIR)</div>'
        + '<div style="font-size:9px;font-weight:600;color:#6b7280;margin-top:1px;">Division of Negros Occidental</div>'
        + '</div>'
        + '<div style="display:flex;align-items:flex-start;gap:12px;margin-top:10px;">'
        + '<div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:120px;">'
        + qrImgHtml
        + `<div style="margin-top:4px;font-family:monospace;font-size:8px;line-height:1.2;color:#9ca3af;text-align:center;word-break:break-all;">${qrText}</div>`
        + '</div>'
        + '<div style="flex:1;min-width:0;">'
        + `<div style="font-size:13px;font-weight:800;text-transform:uppercase;color:#111827;line-height:1.25;margin-bottom:8px;word-break:break-word;">${itemName}</div>`
        + `<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">ITEM NO.</span><span style="display:block;font-family:monospace;font-size:11px;font-weight:700;color:#2563eb;">${invNo}</span></div>`
        + `<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">SERIAL NO.</span><span style="display:block;font-family:monospace;font-size:10px;font-weight:600;color:#1f2937;">${serialNo}</span></div>`
        + `<div style="margin-bottom:6px;"><span style="display:block;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;">CATEGORY</span><span style="display:block;font-size:10px;font-weight:600;color:#374151;">${category}</span></div>`
        + '</div>'
        + '</div>'
        + '<div style="margin-top:10px;border-top:1px dashed #dbeafe;padding-top:6px;text-align:center;font-size:8px;font-weight:500;color:#9ca3af;">Scan this QR code to view item details &bull; DNHS Property Management System</div>'
        + '</div>';
    }

    frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
    document.body.appendChild(frame);
    const doc = frame.contentDocument ?? frame.contentWindow?.document;
    if (!doc) {
      useToastStore().error('Print failed', 'Could not open the print view. Please try again.');
      frame.remove();
      frame = null;
      return;
    }
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Print QR - DNHS</title>'
      + '<style>@page{size:auto;margin:10mm;}*{box-sizing:border-box;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}html,body{margin:0;padding:0;background:#fff;font-family:ui-sans-serif,system-ui,-apple-system,sans-serif;color:#111827;}body{display:grid;grid-template-columns:repeat(auto-fill,9cm);grid-auto-flow:row;gap:6mm;padding:0;}</style>'
      + `</head><body>${stickersHtml}</body></html>`);
    doc.close();

    await new Promise((resolve) => { window.setTimeout(resolve, 350); });
    frame.contentWindow?.focus();
    frame.contentWindow?.print();
    await new Promise((resolve) => { window.setTimeout(resolve, 800); });
  } finally {
    if (frame) frame.remove();
    printingQr.value = false;
  }
}

function openPrintSelected() {
  const selected = qrSources.value.filter((source) => selectedPrintItemIds.value.includes(source.item_id));
  void printStickers(selected);
}

async function openQrPrintModal() {
  qrPrintSearch.value = '';
  qrPrintSelected.value = [];
  qrPrintError.value = '';
  qrPrintOpen.value = true;
  await loadQrPrintItems();
}

async function openExportPdfModal() {
  exportPdfPreviewRequestId += 1;
  exportPdfWorkspace.value = workspace.value ?? 'all';
  exportPdfCategory.value = categoryFilter.value ? String(categoryFilter.value) : '';
  exportPdfSelectedIcs.value = [];
  exportPdfIcsSearch.value = '';
  exportPdfIcsError.value = '';
  exportPdfSubmitting.value = false;
  exportPdfPreviewOpen.value = false;
  exportPdfPreviewLoading.value = false;
  exportPdfPreviewError.value = '';
  exportPdfPreviewHtml.value = '';
  exportPdfOpen.value = true;
  exportPdfSignatureValues.value = {};
  await loadExportPdfIcs();
}

function closeExportPdfModal() {
  exportPdfPreviewRequestId += 1;
  exportPdfOpen.value = false;
  exportPdfPreviewOpen.value = false;
  exportPdfPreviewLoading.value = false;
  exportPdfPreviewHtml.value = '';
  exportPdfPreviewError.value = '';
  exportPdfPreviewReady.value = false;
}

function backToExportFilters() {
  try {
    exportPdfSignatureValues.value = readExportPdfSignatureFields();
  } catch (error) {
    console.error('Failed to preserve inventory report signature fields', error);
    useToastStore().error('Could not save edits', 'Keep the report preview open and try again.');
    return;
  }
  exportPdfPreviewRequestId += 1;
  exportPdfPreviewOpen.value = false;
  exportPdfPreviewLoading.value = false;
  exportPdfPreviewReady.value = false;
}

async function loadExportPdfIcs() {
  exportPdfIcsLoading.value = true;
  exportPdfIcsError.value = '';
  try {
    const { data } = await api.get('/custodian/inventory/ics-numbers');
    if (!Array.isArray(data.icsNumbers)) {
      throw new Error('The server returned an invalid ICS number list.');
    }
    exportPdfIcsOptions.value = data.icsNumbers;
  } catch (requestError) {
    exportPdfIcsError.value = requestError?.response?.data?.message
      ?? requestError?.message
      ?? 'Could not load ICS numbers. Try again before selecting ICS filters.';
  } finally {
    exportPdfIcsLoading.value = false;
  }
}

async function exportInventoryPdf() {
  if (exportPdfIcsLoading.value || exportPdfIcsError.value || exportPdfSubmitting.value
    || exportPdfPreviewLoading.value || !exportPdfPreviewReady.value || exportPdfPreviewError.value) {
    return;
  }
  exportPdfSubmitting.value = true;
  try {
    const exportUrl = new URL(exportPdfModalHref.value, window.location.origin);
    const signatureValues = readExportPdfSignatureFields();
    Object.entries(signatureValues).forEach(([field, value]) => {
      exportUrl.searchParams.set(field, value);
    });

    const response = await api.get(`/custodian/inventory/export-pdf${exportUrl.search}`, {
      responseType: 'blob',
      skipToast: true,
    });
    const blobUrl = window.URL.createObjectURL(response.data);
    const downloadLink = document.createElement('a');
    const disposition = response.headers['content-disposition'] ?? '';
    const filename = disposition.match(/filename="?([^";]+)"?/i)?.[1]
      ?? `dnhs_inventory_${exportPdfWorkspace.value}.pdf`;

    downloadLink.href = blobUrl;
    downloadLink.download = filename;
    document.body.appendChild(downloadLink);
    downloadLink.click();
    downloadLink.remove();
    window.setTimeout(() => window.URL.revokeObjectURL(blobUrl), 1000);
    closeExportPdfModal();
  } catch (requestError) {
    console.error('Failed to export inventory PDF', requestError);
    useToastStore().error('Export failed', 'Could not generate the inventory PDF. Please try again.');
  } finally {
    exportPdfSubmitting.value = false;
  }
}

async function reviewInventoryReport() {
  if (exportPdfIcsLoading.value || exportPdfIcsError.value || exportPdfPreviewLoading.value) {
    return;
  }

  exportPdfPreviewOpen.value = true;
  exportPdfPreviewLoading.value = true;
  exportPdfPreviewError.value = '';
  exportPdfPreviewHtml.value = '';
  exportPdfPreviewReady.value = false;
  const requestId = ++exportPdfPreviewRequestId;

  try {
    const exportUrl = new URL(exportPdfModalHref.value, window.location.origin);
    const params = new URLSearchParams(exportUrl.search);
    params.set('preview', '1');
    Object.entries(exportPdfSignatureValues.value).forEach(([field, value]) => {
      params.set(field, value);
    });
    const { data } = await api.get(`/custodian/inventory/export-pdf?${params.toString()}`, {
      responseType: 'text',
      skipToast: true,
    });

    if (requestId !== exportPdfPreviewRequestId) {
      return;
    }

    if (typeof data !== 'string' || !data.includes('<html')) {
      throw new Error('The server returned an invalid report preview.');
    }

    exportPdfPreviewHtml.value = data;
  } catch (requestError) {
    if (requestId !== exportPdfPreviewRequestId) {
      return;
    }
    console.error('Failed to load inventory report preview', requestError);
    exportPdfPreviewError.value = 'Could not load the inventory report preview. Please try again.';
  } finally {
    if (requestId === exportPdfPreviewRequestId) {
      exportPdfPreviewLoading.value = false;
    }
  }
}

function readExportPdfSignatureFields() {
  const previewDocument = exportPdfPreviewFrame.value?.contentDocument;
  if (!previewDocument) {
    throw new Error('The report preview is not available to read.');
  }

  return Object.fromEntries(EXPORT_PDF_SIGNATURE_FIELDS.map((field) => {
    const input = previewDocument.querySelector(`[data-report-field="${field}"]`);
    if (!input || !('value' in input)) {
      throw new Error(`The report preview is missing the ${field} field.`);
    }
    return [field, input.value];
  }));
}

function toggleVisibleExportIcs(checked) {
  const visible = new Set(filteredExportPdfIcs.value);
  exportPdfSelectedIcs.value = checked
    ? [...new Set([...exportPdfSelectedIcs.value, ...visible])]
    : exportPdfSelectedIcs.value.filter((icsNumber) => !visible.has(icsNumber));
}

function closeQrPrintModal() {
  if (printingQr.value) return;
  qrPrintOpen.value = false;
  qrPrintItems.value = [];
  qrPrintSelected.value = [];
  qrPrintSearch.value = '';
  qrPrintError.value = '';
}

async function loadQrPrintItems() {
  qrPrintLoading.value = true;
  qrPrintError.value = '';
  try {
    const ws = workspace.value;
    const pageName = ws === 'all' ? 'all_page' : `${ws}_page`;
    const collected = [];
    let page = 1;
    let lastPage = 1;
    do {
      const { data } = await api.get('/custodian/inventory', {
        params: { workspace: ws, [pageName]: page },
      });
      const pageData = ws === 'all' ? data.allInventoryPage : (data.inventoryPages ?? {})[ws];
      const groups = pageData?.data ?? [];
      for (const group of groups) {
        for (const source of group.sourceItems ?? []) {
          if (!source.qr_code) continue;
          collected.push({
            item_id: source.item_id,
            inventory_item_no: source.inventory_item_no,
            item_name: source.item_name,
            category: { category_name: source.category?.category_name ?? 'Uncategorized' },
            serial_number: source.serial_number,
            quantity: sourceQuantity(source),
            unit: source.unit,
            status: source.status,
            qr_code: source.qr_code,
          });
        }
      }
      lastPage = Number(pageData?.last_page ?? 1);
      page++;
    } while (page <= lastPage);

    // De-duplicate by item_id in case a group appears under multiple paginators.
    const seen = new Set();
    qrPrintItems.value = collected.filter((item) => {
      if (seen.has(item.item_id)) return false;
      seen.add(item.item_id);
      return true;
    });
  } catch (requestError) {
    qrPrintError.value = requestError?.response?.data?.message ?? 'Could not load QR-coded items.';
    useToastStore().error('Could not load QR-coded items', qrPrintError.value);
  } finally {
    qrPrintLoading.value = false;
  }
}

function printQrAll() {
  const selected = qrPrintItems.value.filter((item) => qrPrintSelected.value.includes(item.item_id));
  void printStickers(selected);
}

function pageParam() {
  return workspace.value === 'all' ? 'all_page' : `${workspace.value}_page`;
}

function format(value) {
  return Number(value ?? 0).toLocaleString();
}

// A fully issued record keeps 0 in inventory.quantity because the units live on the
// assignment transaction instead. Keep reading legacy inspection-flagged assignments
// this way until the cleanup migration restores their prior status.
function sourceQuantity(source) {
  const assigned = Number(source?.assigned_quantity ?? 0);
  if (assigned > 0 && ['assigned', 'under_inspection'].includes(source?.status)) {
    return assigned;
  }
  return Number(source?.quantity ?? 0);
}

function formatDate(value) {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Lifespan helpers. Both fields already come from the grouped listing query
// (MAX(lifespan_years) / MAX(expected_end_date)), so this is display only — no
// backend change. The accessor-driven lifespan_status is not serialized to the
// SPA, so the tone here is derived from the end date using the same thresholds
// as Inventory::lifespanStatus (past = expired, within a year = approaching).
function lifespanYears(row) {
  const years = Number(row?.lifespan_years ?? 0);
  return Number.isFinite(years) && years > 0 ? years : 0;
}

function lifespanEndDate(row) {
  const value = row?.expected_end_date;
  if (!value) {
    return null;
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

function lifespanEndLabel(row) {
  const end = lifespanEndDate(row);
  if (!end) {
    return '';
  }

  return `Ends ${end.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}`;
}

function lifespanLabel(row) {
  const years = lifespanYears(row);
  return years ? `${years} yr${years === 1 ? '' : 's'}` : '';
}

function lifespanTone(row) {
  const end = lifespanEndDate(row);
  if (!end) {
    return 'unknown';
  }
  if (end.getTime() < Date.now()) {
    return 'expired';
  }
  if (end.getTime() <= Date.now() + 365 * 24 * 60 * 60 * 1000) {
    return 'approaching';
  }

  return 'healthy';
}

function lifespanChip(row) {
  const years = lifespanLabel(row);
  const ends = lifespanEndLabel(row);
  if (!years && !ends) {
    return '';
  }

  return years && ends ? `${years} · ${ends}` : (years || ends);
}

function lifespanChipClass(row) {
  switch (lifespanTone(row)) {
    case 'expired':
      return 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300';
    case 'approaching':
      return 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300';
    case 'healthy':
      return 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300';
    default:
      return 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300';
  }
}

function lifespanEndClass(row) {
  return lifespanTone(row) === 'expired'
    ? 'text-rose-600 dark:text-rose-400'
    : '';
}

// Lifespan bar: fraction of life remaining, from the same two dates the chip
// used. Null when either date is missing or invalid, and the template falls
// back to the text chip rather than drawing a misleading bar.
function lifespanSpan(row) {
  const end = lifespanEndDate(row);
  const startValue = row?.date_acquired;
  if (!end || !startValue) {
    return null;
  }
  const start = new Date(startValue);
  if (Number.isNaN(start.getTime()) || end.getTime() <= start.getTime()) {
    return null;
  }
  return { start, end };
}

function lifespanRemainingFraction(row) {
  const span = lifespanSpan(row);
  if (!span) {
    return null;
  }
  const now = Date.now();
  if (now >= span.end.getTime()) {
    return 0;
  }
  if (now <= span.start.getTime()) {
    return 1;
  }
  return (span.end.getTime() - now) / (span.end.getTime() - span.start.getTime());
}

function lifespanRemainingLabel(row) {
  const span = lifespanSpan(row);
  if (!span) {
    return '';
  }
  const now = Date.now();
  if (now >= span.end.getTime()) {
    return 'Expired';
  }
  const months = Math.floor((span.end.getTime() - now) / (30.44 * 24 * 60 * 60 * 1000));
  if (months >= 24) {
    return `${(months / 12).toFixed(1)} yrs remaining`;
  }
  if (months >= 1) {
    return `${months} mo${months === 1 ? '' : 's'} remaining`;
  }
  const days = Math.max(1, Math.floor((span.end.getTime() - now) / (24 * 60 * 60 * 1000)));
  return `${days} day${days === 1 ? '' : 's'} remaining`;
}

function lifespanBarTitle(row) {
  const end = lifespanEndDate(row);
  const date = end ? end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
  const remaining = lifespanRemainingLabel(row);
  if (remaining === 'Expired') {
    return date ? `Expired · Expected expiry was ${date}` : 'Expired';
  }
  return date ? `${remaining} · Expected expiry ${date}` : remaining;
}

function lifespanBarWidth(row) {
  const fraction = lifespanRemainingFraction(row);
  if (fraction === null) {
    return '0%';
  }
  return `${(Math.max(0, Math.min(1, fraction)) * 100).toFixed(1)}%`;
}

function lifespanBarFillClass(row) {
  switch (lifespanTone(row)) {
    case 'expired':
      return 'bg-rose-500';
    case 'approaching':
      return 'bg-amber-500';
    case 'healthy':
      return 'bg-emerald-500';
    default:
      return 'bg-gray-400';
  }
}

function formatMovementDate(movement) {
  if (!movement?.created_at) {
    return 'Not recorded';
  }

  const date = new Date(movement.created_at);
  return Number.isNaN(date.getTime()) ? 'Not recorded' : date.toLocaleString();
}

function itemIconMarkup(row) {
  const itemName = String(row.item_name ?? '').toLowerCase();
  const categoryName = String(row.category?.category_name ?? '').toLowerCase();
  const matchedItem = ITEM_ICONS.find(({ terms }) => terms.some((term) => itemName.includes(term)));
  const matchedCategory = CATEGORY_ICONS.find(({ terms }) => terms.some((term) => categoryName.includes(term)));
  const icon = matchedItem?.icon ?? matchedCategory?.icon ?? Package;
  const children = icon.map(([tag, attributes]) => {
    const serializedAttributes = Object.entries(attributes)
      .map(([name, value]) => `${name}="${String(value).replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"`)
      .join(' ');
    return `<${tag}${serializedAttributes ? ` ${serializedAttributes}` : ''} />`;
  }).join('');

  return `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${children}</svg>`;
}

function money(value) {
  return Number(value ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function openQrScanner() {
  qrResult.value = null;
  qrError.value = '';
  qrScannerOpen.value = true;
}

function printIIRUP() {
  iirupPrintOpen.value = true;
}

function openRfqPreview() {
  rfqPrintOpen.value = true;
}

function openItbCategoryDrawer() {
  itbSelectionModalOpen.value = true;
  itbSelectionDrawerOpen.value = false;
  if (!itbItemsLoaded.value && !itbItemsLoading.value) loadItbEligibleItems();
}

function closeItbSelectionModal() {
  itbSelectionModalOpen.value = false;
  itbSelectionDrawerOpen.value = false;
  itbPreviewMode.value = 'html';
  itbPdfPreviewError.value = '';
  clearItbPdfPreview();
}

async function loadItbEligibleItems() {
  itbItemsLoading.value = true;
  itbItemsError.value = '';
  try {
    const collected = [];
    for (const ws of ['disposed', 'ready_to_dispose']) {
      let page = 1;
      let lastPage = 1;
      do {
        const { data } = await api.get('/custodian/inventory', {
          params: { workspace: ws, [`${ws}_page`]: page },
        });
        const pageData = data.inventoryPages?.[ws];
        for (const group of pageData?.data ?? []) {
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
        lastPage = Number(pageData?.last_page ?? 1);
        page += 1;
      } while (page <= lastPage);
    }

    const byCategory = new Map();
    for (const item of collected) {
      const key = String(item.category_id);
      if (!byCategory.has(key)) {
        byCategory.set(key, {
          category_id: item.category_id,
          category_name: item.category_name,
          items: [],
        });
      }
      byCategory.get(key).items.push(item);
    }
    itbEligibleByCategory.value = [...byCategory.values()]
      .sort((a, b) => a.category_name.localeCompare(b.category_name));
    const allowedIds = new Set(collected.map((item) => item.item_id));
    itbSelectedIds.value = itbSelectedIds.value.filter((id) => allowedIds.has(id));
    itbExpandedCategories.value = itbCategoryGroups.value.map((group) => group.category_id);
    itbItemsLoaded.value = true;
  } catch (loadError) {
    console.error('Failed to load ITB disposal-eligible items', loadError);
    itbItemsError.value = 'Could not load disposal-eligible items. Try again.';
  } finally {
    itbItemsLoading.value = false;
  }
}

function toggleItbCategory(categoryId) {
  itbExpandedCategories.value = itbExpandedCategories.value.includes(categoryId)
    ? itbExpandedCategories.value.filter((id) => id !== categoryId)
    : [...itbExpandedCategories.value, categoryId];
}

function isItbCategoryFullySelected(group) {
  return group.items.length > 0 && group.items.every((item) => itbSelectedIds.value.includes(item.item_id));
}

function isItbCategoryPartiallySelected(group) {
  return group.items.some((item) => itbSelectedIds.value.includes(item.item_id)) && !isItbCategoryFullySelected(group);
}

function toggleItbCategorySelection(group) {
  if (isItbCategoryFullySelected(group)) {
    itbSelectedIds.value = itbSelectedIds.value.filter((id) => !group.items.some((item) => item.item_id === id));
  } else {
    const ids = new Set(itbSelectedIds.value);
    group.items.forEach((item) => ids.add(item.item_id));
    itbSelectedIds.value = [...ids];
  }
}

function toggleItbItem(itemId, checked) {
  if (checked) {
    if (!itbSelectedIds.value.includes(itemId)) itbSelectedIds.value = [...itbSelectedIds.value, itemId];
  } else {
    itbSelectedIds.value = itbSelectedIds.value.filter((id) => id !== itemId);
  }
}

function confirmItbItems() {
  if (itbSelectedItems.value.length === 0) return;
  itbConfirmedSelectedIds.value = [...itbSelectedIds.value];
  itbSelectionStep.value = 'confirmed';
  openItbPreview();
}

function addItbRequirement() {
  itbPreviewMetadata.value.additional_requirements.push('');
}

function removeItbRequirement(index) {
  itbPreviewMetadata.value.additional_requirements.splice(index, 1);
}

function editItbItems() {
  itbSelectedIds.value = [...itbConfirmedSelectedIds.value];
  itbConfirmedSelectedIds.value = [];
  itbSelectionStep.value = 'select';
  itbSelectionDrawerOpen.value = true;
}

function openItbPreview() {
  if (itbSelectedIds.value.length === 0) {
    itbPreviewError.value = 'Select at least one eligible item before previewing the ITB.';
    return;
  }

  clearItbPdfPreview();
  itbPreviewMode.value = 'html';
  itbConfirmedSelectedIds.value = [...itbSelectedIds.value];
  itbSelectionDrawerOpen.value = false;
  itbPreviewError.value = '';
}

function clearItbPdfPreview() {
  clearTimeout(itbPdfPreviewRefreshTimer);
  itbPdfPreviewRefreshTimer = null;
  itbPdfPreviewRequestId += 1;
  itbPdfPreviewLoading.value = false;
  itbPdfPreviewCurrent.value = false;
  if (itbPdfPreviewUrl.value) {
    window.URL.revokeObjectURL(itbPdfPreviewUrl.value);
    itbPdfPreviewUrl.value = '';
  }
}

async function generateItbPdfPreview() {
  if (!itbConfirmedSelectedIds.value.length || itbPdfPreviewLoading.value) return;

  const selectedItemsById = new Map(
    itbEligibleByCategory.value.flatMap((group) => group.items).map((item) => [item.item_id, item]),
  );
  const requestId = ++itbPdfPreviewRequestId;
  itbPdfPreviewLoading.value = true;
  itbPdfPreviewError.value = '';

  try {
    const response = await api.post('/custodian/inventory/itb-pdf', {
      selected_ids: [...itbConfirmedSelectedIds.value],
      floor_prices: itbConfirmedSelectedIds.value.map((itemId) => selectedItemsById.get(itemId)?.floor_price),
      metadata: { ...itbPreviewMetadata.value },
    }, {
      responseType: 'blob',
      skipToast: true,
    });

    if (requestId !== itbPdfPreviewRequestId) return;
    const previousUrl = itbPdfPreviewUrl.value;
    itbPdfPreviewUrl.value = window.URL.createObjectURL(
      new Blob([response.data], { type: 'application/pdf' }),
    );
    itbPdfPreviewCurrent.value = true;
    if (previousUrl) window.URL.revokeObjectURL(previousUrl);
  } catch (requestError) {
    if (requestId !== itbPdfPreviewRequestId) return;

    const responseData = requestError?.response?.data;
    if (responseData instanceof Blob) {
      try {
        const details = JSON.parse(await responseData.text());
        itbPdfPreviewError.value = details.message
          ?? Object.values(details.errors ?? {}).flat()[0]
          ?? 'Failed to generate ITB PDF preview.';
      } catch {
        itbPdfPreviewError.value = 'Failed to generate ITB PDF preview. Please try again.';
      }
    } else {
      itbPdfPreviewError.value = responseData?.message
        ?? 'Failed to generate ITB PDF preview. Please try again.';
    }
  } finally {
    if (requestId === itbPdfPreviewRequestId) {
      itbPdfPreviewLoading.value = false;
    }
  }
}

function selectItbPdfPreview() {
  if (!itbSelectedIds.value.length) return;

  const selectedIds = [...itbSelectedIds.value];
  const selectionChanged = selectedIds.length !== itbConfirmedSelectedIds.value.length
    || selectedIds.some((id, index) => id !== itbConfirmedSelectedIds.value[index]);
  itbConfirmedSelectedIds.value = selectedIds;
  itbPreviewMode.value = 'pdf';
  itbPdfPreviewError.value = '';
  if (selectionChanged) {
    itbPdfPreviewRequestId += 1;
    itbPdfPreviewLoading.value = false;
    itbPdfPreviewCurrent.value = false;
    clearTimeout(itbPdfPreviewRefreshTimer);
    itbPdfPreviewRefreshTimer = null;
  }
  if (!itbPdfPreviewCurrent.value && !itbPdfPreviewLoading.value && !itbPdfPreviewRefreshTimer) {
    generateItbPdfPreview();
  }
}

function downloadItbPdf() {
  if (!itbPdfPreviewUrl.value || !itbPdfPreviewCurrent.value) return;

  const link = document.createElement('a');
  link.href = itbPdfPreviewUrl.value;
  link.download = `ITB_${new Date().toISOString().slice(0, 10)}.pdf`;
  document.body.appendChild(link);
  link.click();
  link.remove();
}

function printItbDocument() {
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
      body.itb-printing .itb-preview-page { width: 210mm !important; height: 297mm !important; min-height: 297mm !important; max-height: 297mm !important; max-width: none !important; margin: 0 !important; box-shadow: none !important; overflow: hidden !important; break-after: page; page-break-after: always; }
      body.itb-printing .itb-preview-page:last-child { break-after: auto; page-break-after: auto; }
      body.itb-printing .itb-preview-row { break-inside: avoid; page-break-inside: avoid; }
      body.itb-printing .itb-preview-status,
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

function closeItbPreview() {
  itbPreviewOpen.value = false;
  itbSelectionDrawerOpen.value = false;
  itbPreviewError.value = '';
  itbPreviewMode.value = 'html';
  itbPdfPreviewError.value = '';
  clearItbPdfPreview();
}

function closeQrScanner() {
  qrScannerOpen.value = false;
  qrResult.value = null;
  qrError.value = '';
}

async function lookupQrToken(token) {
  const normalizedToken = String(token ?? '').trim();
  if (!normalizedToken || qrLookupLoading.value) return;

  qrLookupLoading.value = true;
  qrError.value = '';

  try {
    const { data } = await api.get('/custodian/inventory/qr/lookup', {
      params: { token: normalizedToken },
    });

    if (!data.found) {
      qrError.value = data.message ?? 'No item found for this QR code.';
      return;
    }

    qrResult.value = data;
  } catch (requestError) {
    qrError.value = requestError?.response?.data?.message ?? 'Could not look up this QR code. Try again.';
  } finally {
    qrLookupLoading.value = false;
  }
}

function scanAnotherQrCode() {
  qrResult.value = null;
  qrError.value = '';
}

// Disposal audit attached per source row. The serializer keys relations by
// their camelCase method names; the snake_case fallbacks stay for any path
// that already provides them.
function disposalMovementOf(source) {
  return source?.disposalMovement
    ?? source?.latestDisposalMovement
    ?? source?.disposal_movement
    ?? source?.latest_disposal_movement
    ?? null;
}

function disposalReason(row) {
  const reasons = (row?.sourceItems ?? [])
    .map((source) => disposalMovementOf(source)?.notes)
    .filter((note) => typeof note === 'string' && note.trim() !== '');
  return [...new Set(reasons.map((note) => note.trim()))].join(' · ');
}

function openRecordModal(row) {
  recordGroup.value = row;
  selectedPrintItemIds.value = [];
}

// Open end-user damage/loss report on a source row, if any. Only reports
// (records carrying a diagnosis from the report flow) count — custodian
// repair-pipeline records are handled by the repair actions.
function openIssueReport(source) {
  const record = source?.latestMaintenance ?? null;
  if (!record || !['reported', 'in_progress'].includes(record.status) || !record.diagnosis) {
    return null;
  }
  const reporter = record.reportedBy ?? record.reported_by ?? null;
  const name = reporter
    ? ([reporter.first_name, reporter.last_name].filter(Boolean).join(' ') || reporter.username || 'Unknown')
    : 'Unknown';
  return {
    id: record.id ?? record.maintenance_id,
    diagnosis: record.diagnosis,
    reporter: name,
    notes: record.issue_description ?? '',
    evidence: record.evidence ?? [],
  };
}

async function declineIssueReport(source) {
  const report = openIssueReport(source);
  if (!report?.id || reportDecisionId.value !== null) return;

  const reason = window.prompt('Enter the reason for declining this report.');
  if (reason === null) return;
  if (!reason.trim()) {
    useToastStore().error('Reason required', 'Enter a reason before declining this report.');
    return;
  }

  reportDecisionId.value = report.id;
  try {
    const { data } = await api.post(
      `/custodian/maintenance/${report.id}/decline`,
      { reason: reason.trim() },
      { skipToast: true },
    );
    useToastStore().success(
      'Report declined',
      data?.message ?? 'The issue report was closed.',
    );
    recordGroup.value = null;
    forgetPageCache();
    await load();
  } catch (requestError) {
    useToastStore().error(
      'Could not decline',
      requestError?.response?.data?.message ?? 'Could not close this report. Refresh the inventory and try again.',
    );
  } finally {
    reportDecisionId.value = null;
  }
}

function formatIncidentDate(value) {
  if (!value) return '—';
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? String(value)
    : date.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

let incidentDetailsAutoUpdateCleanup = null;

async function positionIncidentDetails() {
  const trigger = incidentDetailsTrigger.value;
  const popover = incidentDetailsPopover.value;
  if (!trigger || !popover) return;

  const { x, y } = await computePosition(trigger, popover, {
    strategy: 'fixed',
    placement: 'right',
    middleware: [
      offset({ mainAxis: 8, crossAxis: 48 }),
      flip({ fallbackPlacements: ['left', 'bottom', 'top'], padding: 12 }),
      shift({ padding: 12 }),
    ],
  });

  if (incidentDetailsTrigger.value === trigger && incidentDetailsPopover.value === popover) {
    incidentDetailsStyle.value = { left: `${x}px`, top: `${y}px`, visibility: 'visible' };
  }
}

function onIncidentDetailsPointerDown(event) {
  const target = event.target;
  if (!(target instanceof Node)) return;
  if (incidentDetailsTrigger.value?.contains(target) || incidentDetailsPopover.value?.contains(target)) return;
  closeIncidentDetails();
}

function onIncidentDetailsKeydown(event) {
  if (event.key !== 'Escape' || !incidentDetailsReport.value) return;
  event.preventDefault();
  event.stopPropagation();
  closeIncidentDetails(true);
}

function closeIncidentDetails(restoreFocus = false) {
  const trigger = incidentDetailsTrigger.value;
  incidentDetailsAutoUpdateCleanup?.();
  incidentDetailsAutoUpdateCleanup = null;
  document.removeEventListener('pointerdown', onIncidentDetailsPointerDown);
  document.removeEventListener('keydown', onIncidentDetailsKeydown);
  incidentDetailsReport.value = null;
  incidentDetailsTrigger.value = null;
  incidentDetailsPopover.value = null;
  incidentDetailsCloseButton.value = null;
  incidentDetailsStyle.value = { left: '0px', top: '0px', visibility: 'hidden' };
  if (restoreFocus) trigger?.focus();
}

async function toggleIncidentDetails(report, event) {
  const trigger = event.currentTarget;
  if (!(trigger instanceof HTMLElement)) return;
  const alreadyOpen = incidentDetailsReport.value?.id === report.id;
  closeIncidentDetails();
  if (alreadyOpen) return;

  incidentDetailsReport.value = report;
  incidentDetailsTrigger.value = trigger;
  await nextTick();
  if (!incidentDetailsReport.value || incidentDetailsReport.value.id !== report.id) return;
  const popover = incidentDetailsPopover.value;
  if (!popover) return;
  document.addEventListener('pointerdown', onIncidentDetailsPointerDown);
  document.addEventListener('keydown', onIncidentDetailsKeydown);
  incidentDetailsAutoUpdateCleanup = autoUpdate(trigger, popover, positionIncidentDetails);
  incidentDetailsCloseButton.value?.focus();
}

function incidentEventLabel(event) {
  if (event.event_type === 'report_submitted') return 'Report submitted';
  if (event.event_type === 'reporter_response_received') return 'Information response received';
  if (event.event_type === 'status_changed' && event.to_status === 'awaiting_reporter') return 'Information requested';
  if (event.event_type === 'physical_inspection_recorded') return 'Physical inspection recorded';
  if (event.event_type === 'incident_handover_confirmed') return 'Handover confirmed';
  if (event.event_type === 'incident_repair_started') return 'Repair started';
  if (event.event_type === 'incident_repairability_reassessed') return 'Repair reassessed as not viable';
  if (event.event_type === 'investigation_action_recorded') return 'Investigation update';
  if (event.event_type === 'incident_processing_blocked') {
    return `Pending ${String(event.metadata?.stage ?? 'processing').replaceAll('_', ' ')}`;
  }
  if (event.event_type === 'status_changed' && event.to_status) {
    if (event.to_status === 'under_investigation') return 'Investigation started';
    if (event.to_status === 'resolved') {
      return `Report resolved: ${String(event.metadata?.outcome ?? 'complete').replaceAll('_', ' ')}`;
    }
    return `Status changed to ${event.to_status.replaceAll('_', ' ')}`;
  }
  return String(event.event_type ?? 'Incident activity').replaceAll('_', ' ');
}

function incidentRelatedRecords(event) {
  const metadata = event.metadata ?? {};
  const references = [
    ['inventory_id', 'Inventory record', 'Inventory item linked to this report.'],
    ['source_inventory_id', 'Original inventory record', 'Original inventory item reported by the user.'],
    ['processing_inventory_id', 'Affected inventory record', 'Separate inventory record used to track the affected units during repair or disposal.'],
    ['assignment_transaction_id', 'Assignment transaction', 'Record of the equipment assignment related to this report.'],
    ['assignment_request_id', 'Assignment request', 'Request associated with the equipment assignment.'],
    ['assignment_return_id', 'Assignment return record', 'Record confirming the assigned equipment was returned to the custodian.'],
    ['maintenance_record_id', 'Repair record', 'Record of repair work related to this report.'],
    ['disposal_movement_id', 'Disposal record', 'Record of the affected equipment being disposed.'],
    ['stock_movement_id', 'Stock movement record', 'Record of the inventory quantity change related to this report.'],
  ];
  const seen = new Set();

  return references.flatMap(([key, label, description]) => {
    const id = Number(metadata[key]);
    if (!Number.isInteger(id) || id < 1 || seen.has(`${label}-${id}`)) {
      return [];
    }
    seen.add(`${label}-${id}`);
    return [{ label, id, description }];
  });
}

function investigationEvidenceForEvent(report, event) {
  const evidenceIds = new Set((event.metadata?.evidence_ids ?? []).map(Number));
  return report.evidence.filter((file) => evidenceIds.has(Number(file.id)));
}

async function loadIncidentQueue() {
  if (incidentQueueLoading.value) return;
  incidentQueueLoading.value = true;
  incidentQueueError.value = '';
  try {
    const { data } = await api.get('/custodian/incident-reports', { skipToast: true });
    if (!Array.isArray(data?.reports)) {
      throw new Error('The incident queue response was invalid.');
    }
    incidentQueue.value = data.reports;
  } catch (requestError) {
    incidentQueueError.value = requestError?.response?.data?.message
      ?? requestError?.message
      ?? 'Could not load incident reports. Refresh and try again.';
  } finally {
    incidentQueueLoading.value = false;
  }
}

async function submitIncidentAction(report, endpoint, payload, successTitle, fallbackMessage) {
  if (!report?.id || incidentActionId.value !== null) return;
  incidentActionId.value = report.id;
  try {
    const { data } = await api.post(endpoint, payload, { skipToast: true });
    useToastStore().success(successTitle, data?.message ?? fallbackMessage);
    const refreshes = [loadIncidentQueue()];
    if (!isIncidentWorkspace.value) {
      refreshes.push(load());
    }
    await Promise.all(refreshes);
    return true;
  } catch (requestError) {
    useToastStore().error(
      'Incident action failed',
      requestError?.response?.data?.message ?? 'Could not update this incident report. Refresh and try again.',
    );
    return false;
  } finally {
    incidentActionId.value = null;
  }
}

async function acceptIncident(report) {
  await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/review`,
    {},
    'Report accepted',
    'The incident is now under custodian review.',
  );
}

async function startIncidentInvestigation(report) {
  await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/start-investigation`,
    {},
    'Investigation started',
    'The Lost or Stolen report is now under investigation.',
  );
}

function openInvestigationActionModal(report) {
  investigationActionReport.value = report;
  investigationActionDetails.value = '';
  investigationEvidenceFiles.value = [];
}

function selectInvestigationEvidence(event) {
  const files = Array.from(event.target.files ?? []);
  event.target.value = '';
  if (files.length > 5) {
    investigationEvidenceFiles.value = [];
    useToastStore().error('Too many files', 'You can attach up to 5 evidence files per investigation update.');
    return;
  }
  investigationEvidenceFiles.value = files;
}

function closeInvestigationActionModal() {
  if (incidentActionId.value !== null) return;
  investigationActionReport.value = null;
  investigationActionDetails.value = '';
  investigationEvidenceFiles.value = [];
}

async function saveInvestigationAction() {
  const report = investigationActionReport.value;
  const details = investigationActionDetails.value.trim();
  if (!report?.id) return;
  if (details.length < 3) {
    useToastStore().error('Update required', 'Enter at least 3 characters describing the investigation action.');
    return;
  }

  const payload = new FormData();
  payload.append('details', details);
  investigationEvidenceFiles.value.forEach((file) => payload.append('evidence[]', file));
  const saved = await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/investigation-actions`,
    payload,
    'Investigation updated',
    'The investigation update was added to the history.',
  );
  if (saved) {
    closeInvestigationActionModal();
  }
}

function openInvestigationResolutionModal(report) {
  investigationResolutionReport.value = report;
  investigationResolutionOutcome.value = 'recovered';
  investigationResolutionDetails.value = '';
}

function closeInvestigationResolutionModal() {
  if (incidentActionId.value !== null) return;
  investigationResolutionReport.value = null;
  investigationResolutionOutcome.value = 'recovered';
  investigationResolutionDetails.value = '';
}

async function saveInvestigationResolution() {
  const report = investigationResolutionReport.value;
  if (!report?.id) return;
  const outcome = investigationResolutionOutcome.value;
  if (!['recovered', 'confirmed_lost_stolen', 'unsubstantiated'].includes(outcome)) {
    useToastStore().error('Outcome required', 'Choose a valid confirmed outcome before closing the investigation.');
    return;
  }

  const saved = await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/resolve-investigation`,
    { outcome, details: investigationResolutionDetails.value.trim() },
    'Investigation closed',
    'The investigation was closed with the selected outcome.',
  );
  if (saved) {
    closeInvestigationResolutionModal();
  }
}

function requestIncidentInformation(report) {
  incidentInformationReport.value = report;
  incidentInformationReason.value = '';
}

function closeIncidentInformationModal() {
  if (incidentActionId.value !== null) return;
  incidentInformationReport.value = null;
  incidentInformationReason.value = '';
}

async function saveIncidentInformationRequest() {
  const report = incidentInformationReport.value;
  const reason = incidentInformationReason.value.trim();
  if (!report?.id) return;
  if (reason.length < 3) {
    useToastStore().error('Reason required', 'Enter at least 3 characters describing the information needed.');
    return;
  }
  const saved = await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/request-information`,
    { reason },
    'Information requested',
    'The reporter has been asked for more information.',
  );
  if (saved) {
    closeIncidentInformationModal();
  }
}

async function declineIncident(report) {
  const reason = window.prompt('Enter the reason for declining this report.');
  if (reason === null) return;
  if (reason.trim().length < 3) {
    useToastStore().error('Reason required', 'Enter at least 3 characters describing why this report is declined.');
    return;
  }
  await submitIncidentAction(
    report,
    `/custodian/maintenance/${report.id}/decline`,
    { reason: reason.trim() },
    'Report declined',
    'The incident report was declined.',
  );
}

function recordIncidentInspection(report) {
  incidentInspectionReport.value = report;
  incidentInspectionFindings.value = '';
  incidentInspectionRepairability.value = report.inspection?.repairability ?? 'pending';
}

function closeIncidentInspectionModal() {
  if (incidentActionId.value !== null) return;
  incidentInspectionReport.value = null;
  incidentInspectionFindings.value = '';
  incidentInspectionRepairability.value = 'pending';
}

async function saveIncidentInspection() {
  const report = incidentInspectionReport.value;
  const findings = incidentInspectionFindings.value.trim();
  if (!report?.id) return;
  if (findings.length < 3) {
    useToastStore().error('Inspection findings required', 'Enter at least 3 characters describing what was found.');
    return;
  }
  const repairability = incidentInspectionRepairability.value;
  if (!['repairable', 'not_repairable', 'pending'].includes(repairability)) {
    useToastStore().error('Invalid repairability', 'Choose repairable, not_repairable, or pending.');
    return;
  }
  const saved = await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/inspection`,
    { findings, repairability },
    'Inspection recorded',
    'Inspection results were added to the report.',
  );
  if (saved) {
    closeIncidentInspectionModal();
  }
}

function confirmIncidentHandover(report) {
  incidentHandoverReport.value = report;
  incidentHandoverDetails.value = '';
}

function closeIncidentHandoverModal() {
  if (incidentActionId.value !== null) return;
  incidentHandoverReport.value = null;
  incidentHandoverDetails.value = '';
}

async function saveIncidentHandover() {
  const report = incidentHandoverReport.value;
  const details = incidentHandoverDetails.value.trim();
  if (!report?.id) return;
  if (details.length < 3) {
    useToastStore().error('Handover details required', 'Record who handed over the item or where it was received.');
    return;
  }
  const saved = await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/handover`,
    { details },
    'Handover recorded',
    'Affected units were received and isolated.',
  );
  if (saved) {
    closeIncidentHandoverModal();
  }
}

async function startIncidentRepair(report) {
  const notes = window.prompt(
    'Optional note for the repair technician.',
    report.inspection?.findings ?? '',
  );
  if (notes === null) return;
  await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/start-repair`,
    { notes: notes.trim() },
    'Repair started',
    'Repair started for only the affected units.',
  );
}

async function completeIncidentRepair(report) {
  const processingInventoryId = Number(report.handover?.processing_inventory_id);
  if (!Number.isInteger(processingInventoryId) || processingInventoryId < 1) {
    useToastStore().error('Repair record unavailable', 'This report has no linked inventory record for repair completion.');
    return;
  }
  const repairNotes = window.prompt('Describe the completed repair and its result.');
  if (repairNotes === null) return;
  if (repairNotes.trim().length < 3) {
    useToastStore().error('Repair resolution required', 'Enter at least 3 characters describing the repair result.');
    return;
  }
  const costText = window.prompt('Optional repair cost. Leave blank if unknown.', '');
  if (costText === null) return;
  const payload = { repair_notes: repairNotes.trim() };
  if (costText.trim() !== '') {
    const cost = Number(costText);
    if (!Number.isFinite(cost) || cost < 0) {
      useToastStore().error('Invalid repair cost', 'Enter a non-negative number or leave the cost blank.');
      return;
    }
    payload.maintenance_cost = cost;
  }
  await submitIncidentAction(
    report,
    `/custodian/inventory/${processingInventoryId}/mark-repaired`,
    payload,
    'Repair completed',
    'The affected units were repaired and the incident resolved.',
  );
}

async function confirmIncidentRepairNotViable(report) {
  const findings = window.prompt('Why can the affected units not be repaired? Record the reassessment findings.');
  if (findings === null) return;
  if (findings.trim().length < 3) {
    useToastStore().error('Findings required', 'Enter at least 3 characters explaining why repair is not viable.');
    return;
  }
  await submitIncidentAction(
    report,
    `/custodian/incident-reports/${report.id}/repair-not-viable`,
    { findings: findings.trim() },
    'Ready for disposal',
    'The affected units were moved to the existing disposal process.',
  );
}

function isMaintenanceEligible(source, row = maintenanceGroup.value) {
  const eligibility = source.category?.is_maintenance_eligible ?? row?.category?.is_maintenance_eligible;
  return eligibility !== false && eligibility !== 0;
}

function hasMaintenanceEligibleItems(row) {
  return (row.sourceItems ?? []).some((source) =>
    source.status === 'available' && isMaintenanceEligible(source, row),
  );
}

// Change 1: partially issued stock reads in the Assigned tab. A row is shown
// as assigned when it is fully assigned, or when the Assigned workspace lists
// an `available` record that still has units issued out (its remainder stays
// under Available). Every branch below keys off this, never raw status alone.
function assignedOutQty(row) {
  return Number(row?.assigned_quantity ?? 0);
}

function rowAssignees(row) {
  const names = (row?.sourceItems ?? [])
    .flatMap((source) => String(source.active_assignee_summary ?? '').split(','))
    .map((part) => part.replace(/\s*\(\d+\)\s*$/, '').trim())
    .filter(Boolean);
  return [...new Set(names)].join(', ');
}

function hasIssuedOut(row) {
  // Under repair joins available here: repair can be approved while units are
  // still issued out, and those rows must show the holder breakdown too.
  return (row?.status === 'available' || row?.status === 'under_repair') && assignedOutQty(row) > 0;
}

function isPartialAssigned(row) {
  return workspace.value === 'assigned' && hasIssuedOut(row);
}

function displayStatus(row) {
  return isPartialAssigned(row) ? 'assigned' : row?.status;
}

function openMaintenanceModal(row) {
  maintenanceGroup.value = row;
  maintenanceItemIds.value = [];
  maintenanceIssue.value = '';
  maintenanceNotes.value = '';
  maintenanceError.value = '';
}

function closeMaintenanceModal() {
  if (sendingToMaintenance.value) return;
  maintenanceGroup.value = null;
  maintenanceItemIds.value = [];
  maintenanceIssue.value = '';
  maintenanceNotes.value = '';
  maintenanceError.value = '';
}

async function sendSelectedToMaintenance() {
  if (!maintenanceItemIds.value.length || !maintenanceIssue.value.trim() || sendingToMaintenance.value) return;

  sendingToMaintenance.value = true;
  maintenanceError.value = '';
  const selectedSources = maintenanceEligibleItems.value
    .filter((source) => maintenanceItemIds.value.includes(source.item_id));

  if (!selectedSources.length) {
    maintenanceError.value = 'Select at least one eligible available item record.';
    sendingToMaintenance.value = false;
    return;
  }

  try {
    await api.post(
      `/custodian/inventory/${selectedSources[0].item_id}/send-to-maintenance`,
      {
        inventory_ids: selectedSources.map((source) => source.item_id),
        issue_description: maintenanceIssue.value.trim(),
        notes: maintenanceNotes.value.trim() || null,
      },
    );
    maintenanceGroup.value = null;
    maintenanceItemIds.value = [];
    maintenanceIssue.value = '';
    maintenanceNotes.value = '';
    maintenanceError.value = '';
    forgetPageCache();
    await load();
  } catch (requestError) {
    maintenanceError.value = requestError?.response?.data?.message
      ?? 'Could not send the selected items for repair. Refresh the inventory and try again.';
  } finally {
    sendingToMaintenance.value = false;
  }
}

function openMaintenanceActionModal(row, action) {
  maintenanceActionGroup.value = row;
  maintenanceAction.value = action;
  maintenanceActionItemIds.value = [];
  maintenanceRepairNotes.value = '';
  maintenanceCost.value = null;
  maintenanceDisposalNotes.value = '';
  maintenanceActionError.value = '';
}

function closeMaintenanceActionModal() {
  if (maintenanceActionBusy.value) return;
  maintenanceActionGroup.value = null;
  maintenanceActionItemIds.value = [];
  maintenanceActionError.value = '';
}

function openDisposeModal(row) {
  disposeGroup.value = row;
  disposeItemIds.value = [];
  disposeNotes.value = '';
  disposeError.value = '';
}

function closeDisposeModal() {
  if (disposing.value) return;
  disposeGroup.value = null;
  disposeItemIds.value = [];
  disposeNotes.value = '';
  disposeError.value = '';
}

async function confirmDispose() {
  if (!disposeItemIds.value.length || disposing.value) return;

  disposing.value = true;
  disposeError.value = '';
  const selectedSources = disposeEligibleItems.value
    .filter((source) => disposeItemIds.value.includes(source.item_id));

  if (!selectedSources.length) {
    disposeError.value = 'Select at least one record that is still ready to dispose.';
    disposing.value = false;
    return;
  }

  try {
    await api.post(`/custodian/inventory/${selectedSources[0].item_id}/dispose`, {
      inventory_ids: selectedSources.map((source) => source.item_id),
      notes: disposeNotes.value.trim() || null,
    });
    disposeGroup.value = null;
    disposeItemIds.value = [];
    disposeNotes.value = '';
    disposeError.value = '';
    forgetPageCache();
    await load();
  } catch (requestError) {
    disposeError.value = requestError?.response?.data?.message
      ?? 'Could not dispose the selected items. Refresh the inventory and try again.';
  } finally {
    disposing.value = false;
  }
}

async function confirmMaintenanceAction() {
  if (!maintenanceActionItemIds.value.length || maintenanceActionBusy.value) return;
  if (maintenanceAction.value === 'dispose' && !maintenanceDisposalNotes.value.trim()) return;

  maintenanceActionBusy.value = true;
  maintenanceActionError.value = '';
  const selectedSources = maintenanceActionItems.value
    .filter((source) => maintenanceActionItemIds.value.includes(source.item_id));

  if (!selectedSources.length) {
    maintenanceActionError.value = 'Select at least one item record that is still under repair.';
    maintenanceActionBusy.value = false;
    return;
  }

  const routeSuffix = maintenanceAction.value === 'repair' ? 'mark-repaired' : 'mark-ready-to-dispose';
  const payload = maintenanceAction.value === 'repair'
    ? {
        inventory_ids: selectedSources.map((source) => source.item_id),
        repair_notes: maintenanceRepairNotes.value.trim() || null,
        ...(maintenanceCost.value !== null && maintenanceCost.value !== '' ? { maintenance_cost: Number(maintenanceCost.value) } : {}),
      }
    : {
        inventory_ids: selectedSources.map((source) => source.item_id),
        notes: maintenanceDisposalNotes.value.trim(),
      };

  try {
    await api.post(`/custodian/inventory/${selectedSources[0].item_id}/${routeSuffix}`, payload);
    maintenanceActionGroup.value = null;
    maintenanceActionItemIds.value = [];
    maintenanceRepairNotes.value = '';
    maintenanceCost.value = null;
    maintenanceDisposalNotes.value = '';
    maintenanceActionError.value = '';
    forgetPageCache();
    await load();
  } catch (requestError) {
    maintenanceActionError.value = requestError?.response?.data?.message
      ?? 'Could not update the selected maintenance items. Refresh and try again.';
  } finally {
    maintenanceActionBusy.value = false;
  }
}

function openReturnModal(row, fromQr = false) {
  returnGroup.value = row;
  returnFromQr.value = fromQr;
  const firstAssignment = (row.sourceItems ?? [])
    .flatMap((source) => source.receive_return_assignments ?? [])[0];
  returnTransactionId.value = firstAssignment ? String(firstAssignment.transaction_id) : '';
  returnNotes.value = '';
  returnError.value = '';
}

function openQrReturnModal() {
  const item = qrResult.value;
  if (!item?.return_assignments?.length) return;

  qrScannerOpen.value = false;
  openReturnModal({
    item_name: item.item_name,
    sourceItems: [{
      item_id: item.item_id,
      receive_return_assignments: item.return_assignments,
    }],
  }, true);
}

function closeReturnModal() {
  if (returning.value) return;
  const returnToQrScanner = returnFromQr.value;
  returnGroup.value = null;
  returnFromQr.value = false;
  returnTransactionId.value = '';
  returnNotes.value = '';
  returnError.value = '';

  if (returnToQrScanner) {
    qrResult.value = null;
    qrManualToken.value = '';
    qrError.value = '';
    qrScannerOpen.value = true;
  }
}

async function confirmReturn() {
  const assignment = selectedReturnAssignment.value;
  if (!assignment || returning.value) return;

  returning.value = true;
  returnError.value = '';
  try {
    const endpoint = assignment.is_manual_return
      ? `/custodian/transactions/${assignment.transaction_id}/manual-return`
      : '/custodian/inventory/receive-return';
    const payload = assignment.is_manual_return
      ? { notes: returnNotes.value.trim() || null }
      : {
          transaction_id: assignment.transaction_id,
          return_quantity: assignment.remaining_quantity,
          notes: returnNotes.value.trim() || null,
        };
    const { data } = await api.post(
      endpoint,
      payload,
      // A "Return recorded" toast follows below; skipToast stops the interceptor
      // from repeating the same backend message a second time.
      { skipToast: true },
    );
    returnGroup.value = null;
    returnFromQr.value = false;
    returnTransactionId.value = '';
    returnNotes.value = '';
    useToastStore().success('Return recorded', data.message ?? 'The assigned item return was recorded.');
    forgetPageCache();
    await load();
  } catch (requestError) {
    returnError.value = requestError?.response?.data?.message
      ?? 'Could not record this return. The assignment may have changed; refresh and try again.';
  } finally {
    returning.value = false;
  }
}

async function load(options = {}) {
  const params = inventoryCacheParams();
  await loadCachedPage({
    page: inventoryCachePage,
    params,
    background: options.background === true,
    fetchData: async () => {
      const { data } = await api.get('/custodian/inventory', {
        params: { workspace: params.workspace, [pageParam()]: params.page },
      });
      return data;
    },
    applyData: applyInventoryPayload,
    isCurrent: () => {
      const now = inventoryCacheParams();
      return now.workspace === params.workspace && now.page === params.page;
    },
    onStart: () => {
      loading.value = true;
      error.value = '';
    },
    onDone: () => {
      loading.value = false;
    },
    onError: (requestError) => {
      error.value = requestError?.response?.data?.message ?? 'Could not load inventory.';
    },
  });
}

function switchWorkspace(next) {
  animationsLive.value = true;
  if (workspace.value === next) {
    return;
  }
  workspace.value = next;
  page.value = 1;
  selection.value = [];
  if (['incident_reports', 'investigations'].includes(next)) {
    loadIncidentQueue();
  }
}

// Sliding active-tab pill: measured from the rendered button so it tracks
// label/badge widths, wrapping, and dark mode without hardcoded sizes.
const tabStripRef = ref(null);
const pillStyle = ref({ opacity: '0' });
const animationsLive = ref(false);

function updatePill() {
  const strip = tabStripRef.value;
  if (!strip) return;
  const active = strip.querySelector(`[data-workspace-tab="${workspace.value}"]`);
  const next = !active
    ? { opacity: '0' }
    : {
      opacity: '1',
      left: `${active.offsetLeft}px`,
      width: `${active.offsetWidth}px`,
    };
  const prev = pillStyle.value;
  if (prev.opacity === next.opacity && prev.left === next.left && prev.width === next.width) {
    return;
  }
  pillStyle.value = next;
}

let pillObserver = null;

onMounted(() => {
  nextTick(updatePill);
  if (typeof ResizeObserver !== 'undefined' && tabStripRef.value) {
    pillObserver = new ResizeObserver(() => updatePill());
    pillObserver.observe(tabStripRef.value);
  } else {
    window.addEventListener('resize', updatePill);
  }
});

onUnmounted(() => {
  closeIncidentDetails();
  pillObserver?.disconnect();
  window.removeEventListener('resize', updatePill);
});

watch(workspace, () => {
  closeIncidentDetails();
  nextTick(updatePill);
});

function selectWorkspace(next) {
  switchWorkspace(next);
  workspaceMenuOpen.value = false;
}

function goToPage(next) {
  page.value = next;
}

function askDelete(row) {
  deleteGroup.value = row;
  deleteError.value = '';
  deleteItemIds.value = [];
}

function closeDeleteModal() {
  if (deleting.value) return;
  deleteGroup.value = null;
  deleteItemIds.value = [];
  deleteError.value = '';
}

function askEdit(row) {
  editItemId.value = row.source_item_id ?? row.item_id;
}

async function doDelete() {
  const selectedIds = confirmBulk.value ? selection.value : deleteItemIds.value;
  const deleteContext = confirmBulk.value ? 'bulk' : 'group';
  if (!selectedIds.length || deleting.value) return;

  deleting.value = true;
  deleteError.value = '';

  try {
    await api.delete(`/custodian/inventory/${selectedIds[0]}`, {
      data: { selected_item_ids: selectedIds },
    });
    selection.value = [];
    deleteGroup.value = null;
    deleteItemIds.value = [];
    confirmBulk.value = false;
    forgetPageCache();
    await load();
  } catch (requestError) {
    const message = requestError?.response?.data?.message ?? 'Could not delete the selected inventory records.';
    if (deleteContext === 'group') {
      deleteError.value = message;
    } else {
      useToastStore().error('Error', message);
    }
  } finally {
    deleting.value = false;
  }
}

function onStockInSaved() {
  stockInOpen.value = false;
  forgetPageCache();
  const alreadyAtDefaultList = workspace.value === 'all' && page.value === 1;
  search.value = '';
  categoryFilter.value = '';
  selection.value = [];
  workspace.value = 'all';
  page.value = 1;

  if (alreadyAtDefaultList) {
    load();
  }
}

function onEditSaved() {
  editItemId.value = null;
  forgetPageCache();
  load();
}

watch([workspace, page], () => {
  if (!isIncidentWorkspace.value) {
    load();
  }
});
onMounted(() => {
  load();
  loadIncidentQueue();
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

.workspace-enter-active {
  transition: opacity 0.18s ease-out, transform 0.18s ease-out;
}
.workspace-leave-active {
  transition: opacity 0.15s ease-in, transform 0.15s ease-in;
}
.workspace-enter-from {
  opacity: 0;
  transform: translateY(4px);
}
.workspace-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

@keyframes workspace-badge-pop {
  0% { transform: scale(1); }
  40% { transform: scale(1.25); }
  100% { transform: scale(1); }
}
.tab-badge-pop {
  animation: workspace-badge-pop 0.15s ease-out;
}

@media (prefers-reduced-motion: reduce) {
  .workspace-enter-active,
  .workspace-leave-active {
    transition: none;
  }
  .tab-badge-pop {
    animation: none;
  }
}
</style>
