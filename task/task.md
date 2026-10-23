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

7. **Retire the old inspection workflow safely — implemented**
   - Retire the old Inspector queue, QR inspection lookup, and mark-inspected actions; use Arrival Inspections as the Inspector landing page.
   - Remove custodian controls, endpoints, and service actions used only for old inventory flagging and repair recommendations; preserve IAR approval.
   - Preserve general inventory repair/disposal, end-user issue reporting, and normal Custodian QR lookup.
   - Keep existing `under_inspection` records readable and assigned quantities accurate until task 8's reviewed cleanup migration restores their prior statuses.
   - Keep Inspector navigation and routes aligned with Arrival Inspections, Reports, and Profile; the retired inventory route is no longer registered.

8. **Reconcile existing inspection records — migration implemented and locally verified**
   - Restore inventory status only when it is still `under_inspection`; use the saved prior status and fail safely on unknown prior statuses instead of guessing.
   - Close open flagged records as cancelled and pending repair recommendations as declined, recording resolution timestamps. Leave the deciding user empty because the migration is an automated cleanup, not a custodian action.
   - Test repeatability, transaction rollback on invalid data, preservation of already-resolved/unrelated records, and protection of inventory statuses changed independently.
   - Production remains pending: review live record counts and the migration's proposed effects, obtain approval, then schedule deployment. Do not run against production before that review.

9. **Verify the complete workflow — locally verified**
   - Verify Inspector/Custodian role access, ownership, required item descriptions/units, positive quantities, and unique IAR numbers.
   - Verify complete acceptance, partial acceptance, fully rejected deliveries, correction/resubmission, approval signature, and read-only finalization.
   - Verify IAR PDF response/printing eligibility, report data persistence, and duplicate-submission protection.
   - Verify Stock In is blocked before approval, sources are limited to approved unposted IARs, accepted quantities and source links are preserved, stock movements are recorded, and duplicate posting is rejected.
   - Verify Inspector Reports/Profile, normal inventory QR lookup, existing repair/disposal actions, end-user issue reporting, and legacy inspection cleanup isolation.
   - Validation passed locally: 70 focused tests (375 assertions) across IAR workflow/data/PDF, SPA role/shell, issue reporting, repair/disposal, QR lookup, and Stock In/ledger tests. Cleanup migration tests separately pass: 2 tests (22 assertions).
   - Production cleanup remains gated on live-record review and approval as stated in task 8.











## End-User Incident Report Workflow

### Goal

Let end users report incidents for assets assigned to them, then give Property Custodians a traceable review and resolution workflow. A submitted report must not change inventory or assignment status by itself. Apply changes only after the custodian confirms the outcome, and only to the affected quantity.

### Tasks

1. **Confirm existing incident-report behavior and data — done**
   - Map the current end-user report form, API, `MaintenanceRecord` persistence, Custodian report views/actions, inventory statuses, assignment quantities, and notification patterns.
   - Identify which existing data and behavior can be extended; avoid duplicating incident records or conflating these reports with the retired inspection workflow.
   - Document any compatibility requirements for existing reports before changing their lifecycle.

   **Findings and compatibility requirements**
   - The My Assigned Assets SPA form offers only Damaged and Lost, affected quantity, and optional notes; its request posts to the session-authenticated `/api/end-user/inventory/{itemId}/report` route. The same controller action is also registered for the legacy web route. `routes/api.php` is not the SPA resource API; the authenticated SPA routes are registered in `routes/web.php`.
   - `EndUserController::reportIssue` checks assignment ownership using the inventory assignee or an approved assignment, derives a quantity limit from an approved assignment or assigned transaction (falling back to inventory quantity), rejects a second open report by the same user for the same item, and creates a `MaintenanceRecord` with status `reported`. Submission does not update inventory status, assignment quantity, or stock movements.
   - Incident reports already persist in `maintenance_records`: `inventory_id`, `reported_by`, nullable `quantity`, `diagnosis` (currently `damaged` or `lost`), `issue_description`, `status`, and timestamps. Quantity is nullable because custodian-initiated repair records use the same table without an incident quantity. Keep these records and extend/identify them; do not create a parallel incident table or reinterpret records with a null diagnosis as end-user reports.
   - The Custodian Inventory view shows the latest maintenance record on an inventory row when it has a diagnosis and status `reported` or `in_progress`. Its incident action is decline only; declining changes status to `cancelled` and sets `completed_at`, without a reason or separate decision history. Existing repair actions operate on maintenance records without an incident diagnosis and use the statuses `reported`, `in_progress`, and `completed`.
   - Existing reports must retain their `reported`/`in_progress`/`cancelled`/`completed` values and remain distinguishable from repair-pipeline records. A new lifecycle must map or migrate these states explicitly; it must not reopen declined (`cancelled`) reports or treat old `completed` repairs as unresolved incidents.
   - Inventory uses statuses including `assigned`, `under_repair`, `ready_to_dispose`, `available`, and `disposed`. An end-user report currently leaves the inventory status alone. The report quantity is stored, but open-report uniqueness is item-level rather than quantity-level, and dashboard condition counts count open report rows rather than affected units. Preserve no-change-on-submission behavior and account for these legacy semantics when introducing partial-quantity processing.
   - The Custodian view loads only `latestMaintenance`, so adding report history must not hide older open reports. Existing repair and disposal operations work at inventory-record level; partial incident outcomes must not be applied through those whole-record actions without a safe quantity-aware path.
   - No incident-specific notification calls or notification history were found in the report submission/decision path. Do not claim notifications have been sent or duplicate an existing incident channel; introduce and test one as a separate lifecycle capability if required.
   - `inspection_records` is a separate legacy workflow; a migration resolves its open inspection flags and restores prior inventory statuses. Arrival inspections are another distinct process. Do not store incident reports in either inspection system or revive inspection-driven status changes as part of incident submission.
   - **Summary for you:** We checked how reports work today and recorded what must stay compatible. Incident reports continue to use the existing maintenance records and remain separate from inspection records.

2. **Define incident lifecycle, data, and quantity rules — done**
   - Define report types: Damaged, Needs Repair, Lost, and Stolen; define lifecycle states for Reported, custodian review, more information requested, declined, repair/disposal processing, Under Investigation, and resolved.
   - Persist the reporter, assigned asset, affected quantity, incident details, evidence references, review decisions/reasons, inspection or investigation actions, status changes, and final outcome with timestamps and actor identities.
   - Define valid transitions and required fields for each action; preserve an auditable history rather than overwriting prior decisions or actions.
   - Ensure affected quantities cannot exceed the reporter's current assigned quantity and that partial-quantity actions never alter unaffected units.
   - Implementation: lifecycle and outcome state are recorded separately from legacy maintenance status; transitions and required details are enforced by `IncidentLifecycleService`; submission and custodian decisions append actor-attributed timeline events; legacy reports are mapped without inventing historical actors or outcomes. Quantity validation uses the reporter's active assignment, and reporting/transitions do not mutate inventory or assignment quantities. Quantity-aware partial inventory resolution remains a later processing task.
   - **Summary for you:** Reports now have clear statuses and rules for moving between them. The system records who submitted or changed a report, and reporting alone does not change the asset or its assigned quantity.

3. **Extend end-user report submission — done**
   - Allow a user to select only an asset currently assigned to them from My Assigned Assets.
   - Add all four incident types, affected quantity when applicable, incident details, and optional supporting evidence.
   - Validate assignment ownership, quantity, evidence type/size, and required details on the server.
   - Save as Reported without changing inventory status, assignment quantity, or disposition.
   - Show the submitted report and its current status to the reporter.
   - Implementation: the assigned-assets report form supports all four types, required incident details, affected quantity, and an optional JPG/PNG/PDF attachment (5 MB maximum). Server validation checks assignment ownership and active assigned quantity; evidence is stored on the private local disk with metadata. The assigned-items API returns only the signed-in reporter's reports and the SPA displays their current status. Submission does not alter inventory or assignment quantities.
   - **Summary for you:** End users can report damage, repair needs, loss, or theft for items assigned to them, add details and a file, and see their reports' statuses. Submitting a report does not move or change the item.

4. **Add evidence storage and access controls — done**
   - Store optional evidence using the application's existing secure file-storage conventions; persist metadata and associate files with the report.
   - Restrict viewing and downloading to authorized reporters and custodians; validate uploads and avoid exposing storage paths or public files.
   - Test reports with no evidence, valid evidence, invalid uploads, and unauthorized access.
   - Implementation: evidence downloads use an authenticated route that permits only the report's reporter or a Property Custodian. Files are downloaded from their private storage disk; file paths and disk names are hidden from serialized evidence metadata. The reporter and Custodian views now show authorized download links, and tests cover both allowed roles, another user's denial, unauthenticated access, and path secrecy.
   - **Summary for you:** You and Property Custodians can download files attached to a report. Other end users cannot access them, and the private storage location is not exposed.

5. **Build the Custodian review queue and decisions — done**
   - Show Reported incidents with the assigned asset, reported quantity, details, and authorized evidence access.
   - Let the custodian accept and begin processing, request more information with a reason, or decline with a recorded reason.
   - Enforce role access, valid state transitions, and required decision details server-side.
   - Keep the report open while information, physical inspection, handover, or other prerequisite work is pending.
   - Implementation: added a dedicated Custodian incident queue with the asset, reporter, affected quantity, details, evidence downloads, and audit timeline. Custodians can accept reports into review, request information with a recorded reason, or decline with a recorded reason. Server-side role checks and lifecycle transitions apply; reports remain open while review or reporter information is pending. Acceptance and triage do not change inventory or assignment quantities.
   - Validation: focused incident workflow tests pass (16 tests, 119 assertions), including queue access, decisions/reasons, invalid repeated transitions, and unchanged inventory/assignment quantities.
   - **Summary for you:** Custodians now have a review queue to see incident details and files, accept reports, ask for more information, or decline with a reason. These decisions are recorded, and the affected asset and assignment are not changed just by reviewing a report.

6. **Process Damaged and Needs Repair reports — done**
   - Provide a physical-inspection record for the custodian or assigned technician, including findings and repairability.
   - Record any required handover from the end user before sending affected units to the existing repair workflow.
   - Track repair through completion and record the resolution; do not treat a report or inspection alone as confirmation that repair started.
   - If confirmed not repairable, move only the affected units to Ready to Dispose and hand them to the existing disposal process without disposing immediately.
   - Keep the report open and record what is pending when inspection, handover, or repair initiation cannot yet proceed.
   - Implementation: Custodians can append inspection findings and repairability without changing stock. Handover is recorded against the reporter's active assignment, then exactly the reported quantity is separated into its own inventory row so repair/disposal actions cannot affect the remaining units. Confirmed repairable units enter the existing maintenance workflow only after handover; its existing repair-completion action resolves the incident with a recorded outcome. Confirmed unrepairable units are marked Ready to Dispose and remain there until the existing disposal action completes. If a repair proves unviable, the Custodian records reassessment findings before affected units can move to disposal. Failed handover or repair initiation records the blocker and keeps the incident open. Further inspection is prevented after handover, and decline/information-request transitions are rejected after custody changes.
   - Validation: incident workflow tests pass (21 tests); Custodian transaction and existing repair/disposal API tests pass (25 tests). Vue SFC compilation, PHP syntax checks, route registration, and `git diff --check` pass.
   - **Summary for you:** Custodians can record inspection results, confirm that the reported units were handed in, and then send only those units for repair or disposal review. If repair cannot work, they record why before sending the units to disposal. Repair completion or final disposal closes the report. Inspecting alone does not change inventory, and unaffected units stay assigned and unchanged.

7. **Add the Lost/Stolen Investigations workspace — implemented**
   - Add a dedicated Investigations tab in Custodian Inventory for reviewed Lost/Stolen reports whose investigation has begun.
   - Starting an investigation records the custodian, timestamp, and transition to Under Investigation; submission alone must not change asset or assignment status.
   - Allow authorized custodians to append dated investigation actions and evidence without erasing prior history.
   - Close an investigation with a required outcome: Recovered, Confirmed Lost/Stolen, or Unsubstantiated.
   - Apply inventory and assignment changes only according to the recorded, confirmed outcome and only to the affected units.
   - Implementation: Added the dedicated Investigations tab and custodian endpoints to start, append updates/evidence, and close Lost/Stolen investigations. Each action is timestamped, attributed to its custodian, appended to the incident timeline, and its evidence is linked to that action. Lost/Stolen reports must be Under Investigation before closure. Recovered keeps the affected quantity assigned to the reporter; Unsubstantiated makes no inventory or assignment changes; Confirmed Lost/Stolen removes only the affected quantity from the reporter's active transaction and linked assignment request, records a stock movement, and marks fully lost assignments accordingly.
   - Validation: `IssueReportWorkflowTest.php` passes (25 tests, 304 assertions), including linked assignment-request coverage for partial and full confirmed loss. `CustodianTransactionsApiTest.php` passes (25 tests, 194 assertions). Production Vite build, route registration, PHP syntax check, and `git diff --check` pass.
   - **Summary for you:** Custodians can investigate lost or stolen items, add updates and evidence, and close the case with a clear outcome. Items only leave the reporter's assignment after the custodian confirms they are lost or stolen, and then only the reported quantity is removed. Recovered or unsubstantiated reports do not change the assignment or inventory.

8. **Record resolution and preserve audit history — implemented**
   - Keep an append-only or otherwise auditable timeline of report submission, review decisions, information requests/responses, physical inspection, handover, repair/disposal actions, investigation actions, status changes, and final resolution.
   - Record the actor and timestamp for each event and link related inventory, assignment, repair, and disposal records where applicable.
   - Verify partial-quantity resolutions preserve the status and assignment of unaffected units.
   - Implementation: Kept incident events append-only, exposed the actor-attributed event timeline to both custodians and the reporting end user, and added an end-user response action for information requests. Submission, handover, repair, lost/stolen resolution, and disposal events now retain related inventory, assignment, repair, and stock-movement IDs in their metadata. Final disposal is linked to the actual disposal stock movement; the existing partial-quantity workflows continue to isolate or resolve only the affected units.
   - Validation: `IssueReportWorkflowTest.php` passes (26 tests, 336 assertions), including reporter response authorization, append-only history, actor/timestamp visibility, related-record IDs, disposal linkage, and preservation of unaffected assignment state. The adjacent `CustodianTransactionsApiTest.php` and `EndUserSpaApiTest.php` pass together (30 tests, 291 assertions); PHP syntax checks and the production Vite build pass. `InventoryReturnsTest.php` reports 7 failures because its view assertions expect legacy HTML routes that now redirect to the SPA.
   - **Summary for you:** Each report now shows who did what and when to both the custodian and reporter, including information requests and replies, decisions, investigation, repair, and disposal. Related record IDs are retained in the event history. Only affected units are changed; unaffected units remain assigned and unchanged.

9. **Notify the end user — implemented**
   - Notify the reporter when the report is received, more information is requested, a custodian decision or investigation update is recorded, and the case is resolved.
   - Reuse the application's established notification channel and provide a link or route to the report details.
   - Avoid duplicate notifications on retries and test each relevant lifecycle transition.
   - Implementation: Added reporter email updates for report submission; custodian review, information requests, declines, investigation, repair/disposal transitions; investigation actions; and resolution. Each email links to My Assigned Items, where the report timeline is available. Delivery is registered after the event transaction commits and recorded against its unique event ID to prevent retry duplicates; missing reporter addresses are logged and failed sends are recorded and surfaced.
   - Validation: `IncidentReporterNotificationTest.php` verifies after-commit delivery and duplicate suppression. `IssueReportWorkflowTest.php` verifies notification content and one email per report, request, review, decline, investigation, repair, disposal, resolution, and investigation-update event.
   - **Summary for you:** Yes—reporters get email when their incident report changes. Each email links back to My Assigned Items so they can read the timeline.

10. **Verify incident workflows and existing behavior — implemented**
    - Test authorized submission, all report types, assignment ownership, quantity limits, evidence validation/access, and the no-status-change-on-submission rule.
    - Test custodian acceptance, information requests, declines, required reasons, role restrictions, and invalid transitions.
    - Test repairable, not-repairable, and pending inspection/handover paths; confirm repair and disposal use existing workflows.
    - Test investigation start, action history, each final outcome, and correct partial-quantity inventory/assignment effects.
    - Test notifications, audit history, and preservation of existing issue-reporting and unrelated inventory workflows.
    - Implementation: Expanded incident workflow coverage for pending repairability inspections and confirmed they do not allow handover or change inventory/assignment state until a conclusive inspection is recorded. The incident suite covers authorized reporters, all report types, quantities, evidence, custodian transitions, repair/disposal paths, all investigation outcomes, partial quantities, timeline immutability, reporter responses, and notifications.
    - Validation: `IssueReportWorkflowTest.php` and `IncidentReporterNotificationTest.php` pass together (28 tests, 358 assertions); the dedicated pending-inspection test passes (1 test, 26 assertions); `CustodianTransactionsApiTest.php` and `EndUserSpaApiTest.php` pass together (30 tests, 291 assertions); SQLite migration regression passes (1 test, 4 assertions). Broader legacy regressions are not fully green: `InventoryReturnsTest.php` has 7 failures where assertions expect legacy HTML views instead of SPA redirects, and `InventoryStockInTest.php` has 6 failures involving existing inventory edit reasons and PDF lifespan fields. These failures are outside the incident workflow changes.
    - **Summary for you:** The report workflow has been tested from submission through investigation, repair/disposal, notification, and resolution, including permissions, evidence, and partial quantities. The incident and current API suites pass. Some separate older inventory tests still fail on legacy view and inventory-edit/PDF expectations.