<style>
	@page {
		size: letter portrait;
		margin: 1in 0.5in 0.5in;
	}

	body {
		margin: 0;
		font-family: "Times New Roman", serif;
		color: #000;
	}

	.rfq-items-page-one th {
		padding: 2px !important;
	}

	.rfq-items-page-one td {
		padding: 1px !important;
	}

	.rfq-supplier td {
		padding: 2px !important;
	}

	.rfq-page-one {
		position: relative;
		padding-top: 0.15in;
	}

</style>

<div class="rfq-page-one">
	@include('pdfs.components.rfq-header')
	<hr style="width: 100%; margin: 0 0 8px; border: 0; border-top: 1px solid #000;">

	<section class="rfq-project-information" style="width: 100%; margin: 0 0 6px; text-align: center; font-family: 'Times New Roman', serif; color: #000;">
	<p style="margin: 0 0 4px; font-size: 10pt; font-weight: bold; text-align: left;">Bids and Awards Committee</p>
	<p style="margin: 0 0 4px; font-size: 10pt; text-align: left;">
		<strong>Project Name:</strong> {{ $metadata['project_name'] }}
	</p>
	<p style="margin: 0; font-size: 9pt;">Escalante City, Negros Occidental, Philippines</p>
	<p style="margin: 0; font-size: 9pt;">Tel. Nos. - {{ $metadata['bac_contact_numbers'] }}</p>
	<p style="margin: 0; font-size: 9pt;">Escalante.city001@deped.gov.ph</p>
	<p style="margin: 0; font-size: 9pt;">Depedescalantecity.weebly.com</p>
	</section>

	<section class="rfq-identification" style="width: 100%; margin: 6px 0; font-family: 'Times New Roman', serif; color: #000;">
		<h1 style=" margin: 0 0 4px; text-align: center; font-size: 10pt; font-weight: bold;"><i><u>Request for Quotation</u></i></h1>
		<div style="width: 30%; margin-left: auto; font-size: 10pt; text-align: left;">

			<p style="margin: 1px 0;">Quotation #: <u>{{ $metadata['quotation_no'] }}</u></p>
			<p style="margin: 1px 0;">Date: <u>{{ $metadata['rfq_date'] }}</u></p>
			<p style="margin: 1px 0;">Mode of Procurement: <u>{{ $metadata['procurement_mode'] }}</u></p>
		</div>
	</section>

	<section class="rfq-recipient-instructions" style="width: 100%; margin: 6px 0; font-family: 'Times New Roman', serif; color: #000; font-size: 9pt; line-height: 1.1;">
		<p style="margin: 0 0 4px;">To all Eligible Service Providers:</p>
		<table style="width: 100%; border-collapse: collapse;">
			<tbody style="font-size: 9pt; line-height: 1;">
				<tr>
					<td style="width: 0.3in; padding: 0 4px 0 0; vertical-align: top;">I.</td>
					<td style="padding: 0; text-align: justify; vertical-align: top; font-size: 9pt; line-height: 1.1;">
						Please quote your lowest price inclusive of VAT on the item/s listed below, subject to the Terms and Conditions of this RFQ, and submit your quotation duly signed by your representative in an envelope not later than {{ $metadata['submission_deadline'] }} at DepEd, Dian-ay National High School, BAC Office, Escalante City, Negros Occidental, Philippines. For more information please call DNHS School-BAC Secretariat at Telephone No.: {{ $metadata['secretariat_contact'] }}. Prospective service provider shall be responsible to verify details of items described below with the School Bids and Awards Committee, Telephone No. {{ $metadata['verification_contact'] }}.
					</td>
				</tr>
			</tbody>
		</table>
	</section>

	<section class="rfq-bac-chair" style="width: 100%; margin: 12px 0 6px; font-family: 'Times New Roman', serif; color: #000; font-size: 10pt;">
		<div style="width: 50%; margin-left: auto; text-align: right;">
			<p style="margin: 0; font-weight: bold;">{{ $metadata['bac_chair_name'] }}</p>
			<p style="margin: 0;">{{ $metadata['bac_chair_title'] }}</p>
		</div>
	</section>

	<section class="rfq-inspection-schedule" style="width: 100%; margin: 4px 0 6px; font-family: 'Times New Roman', serif; color: #000; font-size: 8pt;">
		<p style="margin: 0 0 2px; font-size: 9pt;">II.</p>
		<table style="width: 100%; table-layout: fixed; border-collapse: collapse;">
			<thead>
				<tr>
					<th style="width: 20%; border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle;">Inspection of Unserviceable Properties</th>
					<th style="width: 20%; border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle;">Schedule of Public Auction</th>
					<th style="width: 20%; border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle;">Pull-out/Taking of Awarded Items</th>
					<th style="width: 20%; border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle;">Floor Price</th>
					<th style="width: 20%; border: 1px solid #000; padding: 4px; text-align: center; vertical-align: middle;">Price offered for the item/s</th>
				</tr>
			</thead>
			<tbody style="font-size: 9pt; line-height: 1;">
				<tr>
					<td style="border: 1px solid #000; padding: 2px; text-align: center; vertical-align: top; font-size: 8pt;">{{ $metadata['inspection_schedule'] }}</td>
					<td style="border: 1px solid #000; padding: 2px; text-align: center; vertical-align: top; font-size: 8pt;">{{ $metadata['auction_schedule'] }}</td>
					<td style="border: 1px solid #000; padding: 2px; text-align: center; vertical-align: top; font-size: 8pt;">{{ $metadata['pullout_schedule'] }}</td>
					<td style="border: 1px solid #000; padding: 2px; text-align: center; vertical-align: top; font-size: 8pt;">{{ number_format($floorPrice, 2) }}</td>
					<td style="border: 1px solid #000; padding: 2px; text-align: center; vertical-align: top; font-size: 8pt;">&nbsp;</td>
				</tr>
				<tr>
					<td colspan="1" style="border: 1px solid #000; padding: 4px; text-align: center; font-style: italic; font-weight: bold;">Name of Project:</td>
					<td colspan="4" style="border: 1px solid #000; padding: 2px; font-size: 8pt; font-weight: bold;">{{ $metadata['project_name'] }}</td>
				</tr>
			</tbody>
		</table>
	</section>

	<section class="rfq-supplier" style="width: 100%; margin: 4px 0; font-family: 'Times New Roman', serif; color: #000; font-size: 8.5pt;">
		<p style="margin: 0 0 4px;">This is to submit our price quotations as indicated above subject to the terms and conditions of this RFQ.</p>
		<table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
			<colgroup>
				<col style="width: 12.5%;">
				<col style="width: 12.5%;">
				<col style="width: 12.5%;">
				<col style="width: 12.5%;">
				<col style="width: 12.5%;">
				<col style="width: 12.5%;">
				<col style="width: 12.5%;">
				<col style="width: 12.5%;">
			</colgroup>
			<tbody style="font-size: 9pt; line-height: 1;">
				<tr>
					<td colspan="2" style="border: 1px solid #000; padding: 4px; font-weight: bold;">Service Provider’s Name:</td>
					<td colspan="3" style="border: 1px solid #000; padding: 4px;">{{ $metadata['supplier_name'] }}</td>
					<td style="border: 1px solid #000; padding: 4px; font-weight: bold;">TIN No.:</td>
					<td colspan="2" style="border: 1px solid #000; padding: 4px;">{{ $metadata['supplier_tin'] }}</td>
				</tr>
				<tr>
					<td colspan="2" style="border: 1px solid #000; padding: 4px; font-weight: bold;">Address:</td>
					<td colspan="6" style="border: 1px solid #000; padding: 4px;">{{ $metadata['supplier_address'] }}</td>
				</tr>
				<tr>
					<td colspan="2" style="border: 1px solid #000; padding: 4px; font-weight: bold;">Telephone No.:</td>
					<td style="border: 1px solid #000; padding: 4px;">{{ $metadata['supplier_phone'] }}</td>
					<td style="border: 1px solid #000; border-right: 0; padding: 4px; font-weight: bold;">Fax no.:</td>
					<td style="border: 1px solid #000; border-left: 0; padding: 4px;">{{ $metadata['supplier_fax'] }}</td>
					<td style="border: 1px solid #000; padding: 4px; font-weight: bold;">E-mail:</td>
					<td colspan="2" style="border: 1px solid #000; padding: 4px;">{{ $metadata['supplier_email'] }}</td>
				</tr>
				<tr>
					<td colspan="3" style="height: 20px; border: 1px solid #000; padding: 4px; text-align: right; vertical-align: bottom;">
						Service Providers’ Authorized Representative Signature over Printed Name
					</td>
                    <td colspan="3" style="height: 20px; border: 1px solid #000; padding: 4px; text-align: center; vertical-align: bottom;"> 
                    {{ $metadata['representative_signature'] }}
                    </td>
					<td style="height: 20px; border: 1px solid #000; border-right: 1px solid #000; padding: 4px; text-align: left; vertical-align: middle; font-weight: bold;">
						Date :
					</td>
					<td style="height: 20px; border: 1px solid #000; border-left: 0; padding: 4px; vertical-align: middle;">{{ $metadata['representative_date'] }}</td>
				</tr>
			</tbody>
		</table>
	</section>

	<section class="rfq-items-page-one" style="width: 100%; margin: 4px 0 0; font-family: 'Times New Roman', serif; color: #000; font-size: 8.5pt;">
		<p style="margin: 0 0 2px; text-align: left; font-size: 9pt; font-weight: bold;">Bids and Awards Committee</p>
		<p style="margin: 0 0 4px; text-align: center; font-size: 9pt; font-weight: bold;">Instruction: Indicate “comply” or “not comply” in the space provided below.</p>
		<table style="width: 100%; table-layout: fixed; border-collapse: collapse; line-height: 1;">
			<colgroup>
				<col style="width: 35%;">
				<col style="width: 8%;">
				<col style="width: 9%;">
				<col style="width: 12%;">
				<col style="width: 11%;">
				<col style="width: 8.33%;">
				<col style="width: 8.33%;">
				<col style="width: 8.34%;">
			</colgroup>
			<thead>
				<tr>
					<th style="border: 1px solid #000; padding: 3px; text-align: center;">ITEMS &amp; DESCRIPTION</th>
					<th style="border: 1px solid #000; padding: 2px; text-align: center;">Unit</th>
					<th style="border: 1px solid #000; padding: 2px; text-align: center;">No. of item/unit</th>
					<th style="border: 1px solid #000; padding: 2px; text-align: center;">Unit Price (ABC) (per month)</th>
					<th style="border: 1px solid #000; padding: 2px; text-align: center;">Price Offered</th>
					<th colspan="3" style="border: 1px solid #000; padding: 3px; text-align: center;">Statement of Compliance (State “Comply” or “Not Comply”)</th>
				</tr>
			</thead>
			<tbody style="font-size: 9pt; line-height: 1;">
				@foreach ($items as $item)
					<tr>
						<td style="border: 1px solid #000; padding: 2px;">{{ $item['description'] }}</td>
						<td style="border: 1px solid #000; padding: 2px; text-align: center;">{{ $item['unit'] }}</td>
						<td style="border: 1px solid #000; padding: 2px; text-align: center;">{{ $item['quantity'] }}</td>
						<td style="border: 1px solid #000; padding: 2px; text-align: right;">{{ number_format((float) $item['unit_price'], 2) }}</td>
						<td style="border: 1px solid #000; padding: 2px; text-align: right;">{{ $item['price_offered'] === '' ? '' : number_format((float) $item['price_offered'], 2) }}</td>
						<td style="border: 1px solid #000; padding: 2px; text-align: center;">{{ $item['compliance'] }}</td>
						<td style="border: 1px solid #000; padding: 2px;"></td>
						<td style="border: 1px solid #000; padding: 2px;"></td>
					</tr>
				@endforeach
				<tr>
					<td style="border: 1px solid #000; padding: 2px; text-align: center;">TOTAL</td>
					<td style="border: 1px solid #000; padding: 2px;"></td>
                    <td style="border: 1px solid #000; padding: 2px;"></td>
					<td style="border: 1px solid #000; padding: 2px; text-align: right; font-weight: bold;">{{ number_format($total, 2) }}</td>
					<td style="border: 1px solid #000; padding: 2px;"></td>
					<td style="border: 1px solid #000; padding: 2px;"></td>
					<td style="border: 1px solid #000; padding: 2px;"></td>
					<td style="border: 1px solid #000; padding: 2px;"></td>
				</tr>
				<tr>
					<td colspan="5" style="border: 1px solid #000; padding: 2px;">*****nothing follows*****</td>
					<td style="border: 1px solid #000; padding: 2px;"></td>
					<td style="border: 1px solid #000; padding: 2px;"></td>
					<td style="border: 1px solid #000; padding: 2px;"></td>
				</tr>
				<tr>
					<th colspan="5" style="border: 1px solid #000; padding: 4px; text-align: center;">ITEMS DESCRIPTION</th>
					<th colspan="3" style="border: 1px solid #000; padding: 4px; text-align: center;">Statement of Compliance (State “Comply” or “Not Comply”)</th>
				</tr>
				<tr>
					<td colspan="5" style="border: 1px solid #000; padding: 4px; font-weight: bold;">Documentary Requirements:</td>
					<td colspan="3" style="border: 1px solid #000; padding: 4px;">&nbsp;</td>
				</tr>
				@foreach ($requirements as $index => $requirement)
					<tr>
						<td colspan="5" style="border: 1px solid #000; padding: 4px;">{{ $index + 1 }}. {{ $requirement['description'] }}</td>
						<td colspan="3" style="border: 1px solid #000; padding: 4px; text-align: center;">{{ $requirement['compliance'] ?? '' }}</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</section>

	<section class="rfq-terms-submission" style="width: 100%; margin: 8px 0 0; font-family: 'Times New Roman', serif; color: #000; font-size: 8.5pt; line-height: 1.2;">
		<p style="margin: 0 0 4px; font-weight: bold;">III. Terms and Conditions</p>
		<p style="margin: 0 0 6px; font-weight: bold;">A. Submission of Requirements</p>
		<p style="margin: 0 0 4px 0.25in; text-align: justify; text-indent: -0.25in;">{{ $terms['submission_address'] }}</p>
		<p style="margin: 0 0 4px 0.25in; text-align: justify; text-indent: -0.25in;">{{ $terms['submission_documents_intro'] }}</p>
		<p style="margin: 0 0 3px 0.5in; text-indent: -0.25in;">{{ $terms['document_mayors_permit_post_qualification'] }}</p>
		<p style="margin: 0 0 3px 0.5in; text-indent: -0.25in;">{{ $terms['document_mayors_permit_payment'] }}</p>
		<p style="margin: 0 0 3px 0.5in; text-indent: -0.25in;">{{ $terms['document_philgeps'] }}</p>
		<p style="margin: 0 0 4px 0.5in; text-indent: -0.25in;">{{ $terms['document_dole'] }}</p>
		<p style="margin: 0; font-style: italic; text-align: justify;">{{ $terms['previous_submission_note'] }}</p>
	</section>

	<section class="rfq-terms-b-e" style="width: 100%; margin: 8px 0 0; font-family: 'Times New Roman', serif; color: #000; font-size: 8.5pt; line-height: 1.2;">
		<p style="margin: 0 0 3px; font-weight: bold;">B. Price Validity</p>
		<p style="margin: 0 0 6px 0.25in; text-align: justify;">{{ $terms['price_validity'] }}</p>

		<p style="margin: 0 0 3px; font-weight: bold;">C. Ocular Inspection</p>
		<p style="margin: 0 0 6px 0.25in; text-align: justify;">{{ $terms['ocular_inspection'] }}</p>

		<p style="margin: 0 0 3px; font-weight: bold;">D. Award</p>
		<p style="margin: 0 0 6px 0.25in; text-align: justify;">{{ $terms['award'] }}</p>

		<p style="margin: 10px 0 8px; font-weight: bold;">Bids and Awards Committee</p>
		<p style="margin: 0 0 4px 0.25in; text-align: justify;">{{ $terms['award_tie_break'] }}</p>
		<p style="margin: 0 0 8px 0.25in; text-align: justify;">{{ $terms['award_absent_bidder'] }}</p>

		<p style="margin: 0 0 3px; font-weight: bold;">E. Delivery of Service</p>
		<p style="margin: 0 0 6px 0.25in; text-align: justify;">{{ $terms['delivery'] }}</p>

		<p style="margin: 0 0 3px; font-weight: bold;">F. Evaluation of Quotations</p>
		<p style="margin: 0 0 3px 0.25in;">{{ $terms['evaluation_intro'] }}</p>
		<p style="margin: 0 0 2px 0.25in;">{{ $terms['evaluation_price'] }}</p>
		<p style="margin: 0 0 2px 0.25in;">{{ $terms['evaluation_compliance'] }}</p>
		<p style="margin: 0 0 2px 0.25in;">{{ $terms['evaluation_documents'] }}</p>
		<p style="margin: 0 0 6px 0.25in;">{{ $terms['evaluation_note'] }}</p>

		<p style="margin: 0 0 3px; font-weight: bold;">G. Instructions</p>
		<p style="margin: 0 0 4px 0.25in; text-align: justify; text-indent: -0.25in;">{{ $terms['instruction_sources'] }}</p>
		<p style="margin: 0 0 4px 0.25in; text-align: justify; text-indent: -0.25in;">{{ $terms['instruction_failure'] }}</p>
		<p style="margin: 0 0 4px 0.25in; text-align: justify; text-indent: -0.25in;">{{ $terms['instruction_taxes'] }}</p>
		<p style="margin: 0 0 8px 0.25in; text-align: justify; text-indent: -0.25in;">{{ $terms['instruction_withholding'] }}</p>

		<p style="margin: 0 0 3px; font-weight: bold;">H. Liquidated Damages</p>
		<p style="margin: 0 0 6px 0.25in; text-align: justify;">{{ $terms['liquidated_damages'] }}</p>

		<p style="margin: 0 0 3px; font-weight: bold;">I. Warranty</p>
		<p style="margin: 0 0 6px 0.25in; text-align: justify;">{{ $terms['warranty'] }}</p>

		<p style="margin: 0 0 3px; font-weight: bold;">J. Payment</p>
		<p style="margin: 0 0 10px 0.25in; text-align: justify;">{{ $terms['payment'] }}</p>

		<p style="margin: 0; text-align: justify;">{{ $terms['reservation'] }}</p>
	</section>

</div>
