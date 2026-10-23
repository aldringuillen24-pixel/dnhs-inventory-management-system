# Inspector Arrival Inspection and IAR Workflow

## Goal

When goods physically arrive, the Inspector records and inspects them in one workflow. Accepted items produce an Inspection and Acceptance Report (IAR). Do not create a separate Incoming Delivery module.

## Tasks

1. **Acceptance and inventory-posting rules — decided**
   - For partial acceptance, generate the IAR for accepted items only; record rejected quantities and findings as part of the inspection.
   - For fully rejected deliveries, save the inspection and rejection findings, but do not generate an IAR.
   - When the Inspector finalizes the IAR, post accepted quantities to inventory immediately. Do not require a separate custodian confirmation as a posting gate.
   - The IAR must include three inspection committee confirmation/signature lines and a separate School Property Custodian acceptance signature. The custodian signature is part of the IAR document and does not delay inventory posting.

2. **Define arrival inspection and item data — implemented**
   - Added separate arrival-inspection and item-line data structures, independent of the old workflow for flagging existing inventory.
   - The inspection stores IAR header, inspection and acceptance details, status, creator/completer, posting timestamp, and the three committee plus custodian printed names/designations. Handwritten signatures are added to the printed document.
   - Each IAR item line stores only Stock/Property No., description, unit, and quantity. The IAR header includes Entity Name, Fund Cluster, Supplier, Contract Title, Requisitioning Office/Department, Responsibility Center Code, IAR No./Date, Invoice No./Date, Inspection Date, Date Received, acceptance, and remarks.
   - IAR numbers are unique; fully rejected inspections can be stored without an IAR number. UI validation, IAR numbering, and idempotent inventory posting remain in later tasks.

3. **Build the Inspector workflow — implemented**
   - Added an Inspector-only Arrival Inspections page and New Inspection entry point; Inspector Reports and Profile remain available.
   - Inspectors can save drafts, reopen their own drafts, record the IAR header and item columns, then record inspection/acceptance details with inspector identity and timestamp.
   - For partial acceptance, enter accepted quantity in the IAR item quantity and record rejected quantities/findings in acceptance remarks.
   - Recorded inspection results can be reviewed and corrected until IAR finalization; finalized records are read-only. IAR generation/finalization and inventory posting remain in Tasks 4–5.

4. **Generate the IAR — in progress**
   - Open New Inspection and Review in an IIRUP-style modal with the HTML IAR preview as the initial view; edit data directly in the preview document.
   - Keep all IAR fields and item lines editable in place until finalization.
   - Generate and preview a printable IAR PDF from saved inspection data and the supplied form.
   - Include accepted item quantities, rejected quantities/findings in acceptance remarks, and the inspection/acceptance results.
   - Include three inspection committee confirmation/signature lines and a separate School Property Custodian acceptance signature.
   - Verify the PDF renders as a letter-size document and gate it to eligible, owned inspections with an IAR number.
   - Use a text-only letterhead until an appropriate Cebu Province logo asset is available; do not use the unrelated Escalante City header artwork.

5. **Handle accepted items in inventory**
   - Implement immediate inventory posting by the Inspector when the IAR is finalized.
   - Add only accepted quantities; never add rejected quantities.
   - Record inventory movements and preserve a link to the originating inspection/IAR.

6. **Retire the old inspection workflow safely**
   - Remove the old Inspector queue, QR inspection lookup, and mark-inspected actions.
   - Remove custodian controls and endpoints that only serve the old flag-existing-inventory and repair-recommendation flow.
   - Preserve unrelated inventory repair, end-user issue reporting, and normal QR lookup behavior.
   - Reconcile the Inspector navigation, routes, and landing page with the new arrival inspection flow.

7. **Reconcile existing inspection records**
   - Review the proposed cleanup migration before deployment.
   - Restore open flagged items to their saved prior status and close those old records as agreed.
   - Decline pending repair recommendations and restore the prior item status, preserving an audit trail.
   - Test the cleanup migration for repeatability and verify that it does not alter unrelated or already-resolved records.
   - Do not run this cleanup against production until its record counts and effects have been reviewed and approved.

8. **Verify the complete workflow**
   - Test role access and validation for inspection lines and quantities.
   - Test pass, partial acceptance, and rejection cases.
   - Test IAR numbering, report contents, printing, and duplicate-submission protection.
   - Test immediate inventory posting, including quantity and movement history.
   - Confirm Inspector Reports/Profile, existing inventory, and unrelated repair and issue-report workflows remain functional.