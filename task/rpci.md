# RPCI Workflow - Small Task Breakdown

## Goal

Build a workflow that collects teacher custody confirmations, records the
Inventory Committee's physical count, helps the Property Custodian reconcile
differences, and prepares a consolidated RPCI for authorized review.

Teacher confirmation is not the official physical count. The committee must
record the verified count, and authorized people must review and certify the
final report.

## Phase 1: Confirm the rules

- [ ] Confirm the count period, count date, response deadline, and reminder schedule.
- [ ] Confirm which inventory types and fund clusters the report must support.
- [ ] Confirm which items are included: stockroom supplies, durable property,
  items assigned to staff, and remaining consumables issued to staff.
- [ ] Confirm the roles allowed to create a count, respond, count, reconcile,
  review, and certify it.
- [ ] Confirm the required RPCI form, report columns, signatories, and submission
  deadlines with the school or division.
- [ ] Decide how teachers receive the sheet and reminders (in-app, email, or both).

**Checkpoint:** Do not generate an official-looking RPCI until these rules and
the report format have been confirmed.

## Phase 2: Create a count cycle

- [ ] Add a count-cycle record with title, fiscal year, count date, deadlines,
  status, and creator.
- [ ] Add cycle statuses: Draft, Open, Counting, Reconciliation, For Review,
  Approved, and Closed.
- [ ] Add a way for an authorized custodian to create, edit, open, and close a cycle.
- [ ] Prevent edits to the cycle setup after it is closed, except through an
  audited correction process.

**Checkpoint:** An authorized custodian can create a draft cycle and open it.

## Phase 3: Prepare teacher custody sheets

- [ ] Identify current items assigned to each active teacher or staff member.
- [ ] Map each item to its inventory type and fund cluster, and flag records that
  are missing that information.
- [ ] Create a dated snapshot of each cycle's item list so later inventory edits
  do not silently change the count being reviewed.
- [ ] Create one custody sheet per teacher, with only that teacher's assigned items.
- [ ] Show item identifiers, description, recorded quantity, and recorded location.
- [ ] Allow the custodian to preview the generated sheets before sending them.
- [ ] Notify each teacher when their sheet is ready and show its due date.

**Checkpoint:** Each teacher can see only their own sheet, and the custodian can
see which sheets were prepared and sent.

## Phase 4: Collect teacher confirmations

- [ ] Let teachers mark each item Present, Missing, Moved, or Needs Correction.
- [ ] Let teachers report actual location, condition, and a note when needed.
- [ ] Let teachers submit their sheet and show submission time and status.
- [ ] Allow a teacher to correct and resubmit before the custodian locks the response.
- [ ] Keep the recorded inventory quantity separate from the teacher's response;
  a teacher confirmation must not change inventory records automatically.
- [ ] Show the custodian who has responded and who is still overdue.
- [ ] Send reminders only to teachers with incomplete sheets.
- [ ] Record notification delivery and prevent repeated reminders for the same
  cycle and reminder step.

**Checkpoint:** Teacher responses are saved, attributable, and visible to the
custodian without being treated as a verified physical count.

## Phase 5: Record the committee's physical count

- [ ] Assign count locations or item groups to committee members.
- [ ] Let authorized committee members enter the actual quantity found for each item.
- [ ] Record who counted, when they counted, and any count notes.
- [ ] Support items not found and items found that are not on the prepared list.
- [ ] Require a recount or explanation for differences, based on the confirmed rules.
- [ ] Keep committee count entries separate from teacher confirmations and book balances.

**Checkpoint:** The system can show the book balance, teacher response, and committee
count as separate pieces of information.

## Phase 6: Reconcile and review differences

- [ ] Compare the book balance with the committee's physical count.
- [ ] Flag shortages, overages, missing items, and mismatched locations.
- [ ] Let the Property Custodian add an explanation and follow-up action for each difference.
- [ ] Preserve original values and log changes with user and timestamp.
- [ ] Let an authorized reviewer return the cycle for correction or approve it.
- [ ] Require all required explanations and count assignments before review.
- [ ] Record review decisions, comments, reviewer, and decision time.

**Checkpoint:** A reviewer can trace every difference from the recorded balance
through the count and reconciliation.

## Phase 7: Prepare and finalize the RPCI

- [ ] Group the reviewed results by the confirmed inventory type and fund cluster.
- [ ] Generate a draft RPCI in the confirmed Appendix 66 format.
- [ ] Validate required fields and totals before allowing finalization.
- [ ] Show certification/signature fields required by the approved form.
- [ ] Allow authorized users to download or print the draft for review.
- [ ] Finalize and lock the approved report; retain who prepared, counted, reviewed,
  and certified it.
- [ ] Allow a corrected version only through a recorded amendment or reopened cycle.

**Checkpoint:** The generated report matches the confirmed official form and can
be traced back to its count entries.

## Phase 8: Test and release

- [ ] Test access rules so teachers cannot see another teacher's sheet or change counts.
- [ ] Test cycle creation, snapshot stability, responses, reminders, counts,
  reconciliation, approval, and report generation.
- [ ] Test empty, duplicate, missing classification, and changed-assignment cases.
- [ ] Test that reminders and report generation do not create duplicate records.
- [ ] Test migrations and the count workflow using the project's supported database.
- [ ] Document setup, permissions, the count process, and deployment requirements.
- [ ] Pilot one count cycle with sample data before using it for an official report.

## Decisions to settle before implementation

- What date and lead time should start the annual count?
- Which assigned items and remaining consumables must appear on a teacher sheet?
- Are teachers and committee members using in-app notifications, email, or both?
- Which role appoints committee members and who is authorized to certify?
- Which exact Appendix 66 version and submission deadlines apply?