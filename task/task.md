# Inspector Arrival Inspection and IAR Workflow

## Goal

When goods physically arrive, the Inspector records and inspects them in one workflow. Accepted items produce an Inspection and Acceptance Report (IAR), which the Property Custodian must review and sign before it can proceed to Stock In. Do not create a separate Incoming Delivery module.

## Tasks

1. **Acceptance and inventory-posting rules — decided**
   - For partial acceptance, generate the IAR for accepted items only; record rejected quantities and findings as part of the inspection.
   - For fully rejected deliveries, save the inspection and rejection findings, but do not generate an IAR.
   - The Inspector submits completed inspection results to the Property Custodian. The custodian must review and sign the IAR before it is approved; returned IARs go back to the Inspector for correction and resubmission.
   - Do not post an IAR to inventory before custodian approval. Stock In integration is a separate follow-up task.
   - The IAR must include three inspection committee confirmation/signature lines and a separate School Property Custodian acceptance signature.

2. **Define arrival inspection and item data — implemented**
   - Added separate arrival-inspection and item-line data structures, independent of the old workflow for flagging existing inventory.
   - The inspection stores IAR header, inspection and acceptance details, status, creator/completer, custodian signature and return details, posting timestamp, and the three committee plus custodian printed names/designations.
   - Each IAR item line stores only Stock/Property No., description, unit, and quantity. The IAR header includes Entity Name, Fund Cluster, Supplier, Contract Title, Requisitioning Office/Department, Responsibility Center Code, IAR No./Date, Invoice No./Date, Inspection Date, Date Received, acceptance, and remarks.
   - IAR numbers are unique; fully rejected inspections can be stored without an IAR number. UI validation, IAR numbering, and idempotent inventory posting remain in later tasks.

3. **Build the Inspector workflow — implemented**
   - Added an Inspector-only Arrival Inspections page and New Inspection entry point; Inspector Reports and Profile remain available.
   - Inspectors can save drafts, reopen their own drafts, record the IAR header and item columns, then record inspection/acceptance details with inspector identity and timestamp.
   - For partial acceptance, enter accepted quantity in the IAR item quantity and record rejected quantities/findings in acceptance remarks.
   - Inspectors can edit drafts and IARs returned for correction. Submitted IARs are locked while awaiting custodian review; approved IARs are read-only.

4. **Generate the IAR — implemented**
   - Open New Inspection and Review in an IIRUP-style modal with the HTML IAR preview as the initial view; edit data directly in the preview document.
   - Keep drafts editable; lock submitted IARs until the custodian returns them for correction.
   - Generate and preview a printable IAR PDF from saved inspection data and the supplied form.
   - Include accepted item quantities, rejected quantities/findings in acceptance remarks, and the inspection/acceptance results.
   - Include three inspection committee confirmation/signature lines and show the custodian's authenticated electronic signature after approval.
   - Verify the PDF renders as a letter-size document and gate it to eligible, owned inspections with an IAR number.

5. **Review and approve IARs — implemented**
   - Show submitted, non-rejected IARs in the Custodian Transactions > IAR Approvals tab.
   - Record the authenticated custodian's signature and approval timestamp; reject approval if the signature does not match the custodian account.
   - Allow the custodian to return an IAR with a required correction reason; allow the Inspector to correct and resubmit it.
   - Keep approved IARs read-only. Approval enables a separate Custodian Stock In action; it does not post stock automatically.

6. **Handle approved items in inventory — implemented**
   - Add a "From approved IAR" option in the Custodian Stock In modal, listing approved IARs not yet posted.
   - Prefill accepted IAR item descriptions, units, and quantities; require the Custodian to supply category, cost, and serial numbers when required.
   - Create Stock In inventory records and movements only after the Custodian saves; preserve links from each inventory record to its IAR and item line.
   - Post each IAR once and mark it posted; reject incomplete item mappings and duplicate submissions.

7. **Retire the old inspection workflow safely**
   - Remove the old Inspector queue, QR inspection lookup, and mark-inspected actions.
   - Remove custodian controls and endpoints that only serve the old flag-existing-inventory and repair-recommendation flow; preserve the new IAR approval workflow.
   - Preserve unrelated inventory repair, end-user issue reporting, and normal QR lookup behavior.
   - Reconcile the Inspector navigation, routes, and landing page with the new arrival inspection flow.

8. **Reconcile existing inspection records**
   - Review the proposed cleanup migration before deployment.
   - Restore open flagged items to their saved prior status and close those old records as agreed.
   - Decline pending repair recommendations and restore the prior item status, preserving an audit trail.
   - Test the cleanup migration for repeatability and verify that it does not alter unrelated or already-resolved records.
   - Do not run this cleanup against production until its record counts and effects have been reviewed and approved.

9. **Verify the complete workflow**
   - Test role access and validation for inspection lines and quantities.
   - Test pass, partial acceptance, and rejection cases.
   - Test IAR numbering, report contents, printing, and duplicate-submission protection.
   - Test that Stock In is available only after custodian approval, including quantity and movement history.
   - Confirm Inspector Reports/Profile, existing inventory, and unrelated repair and issue-report workflows remain functional.