## Current Database Design Gaps

1. **No dedicated maintenance records**
   - No repair details, technician, cost, diagnosis, or completion history.

2. **No maintenance completion workflow**
   - Items can enter `under_maintenance`, but there is no proper return-to-available or repair-failed process.

3. **Request types are implicit**
   - The `requests` table does not identify whether a record is an assignment, transfer, or return.

4. **Statuses are free-form strings**
   - Invalid or inconsistent statuses can be stored because the database has no status constraints.

5. **Assignment data is duplicated**
   - Assignment information exists across `inventory`, `requests`, and `transactions`, creating synchronization risk.

6. **OTP records are linked by email only**
   - `password_reset_otps` has no foreign key to `users`.

7. **Audit references are not enforced**
   - `reference_type` and `reference_id` in `stock_movements` do not guarantee that the referenced record exists.

8. **Quantity constraints are incomplete**
   - Quantities are unsigned but do not have database-level checks requiring values greater than zero.

9. **No maintenance cost or disposal history**
   - Maintenance and disposal actions are recorded generally, but detailed financial and decision history is missing.

10. **No dedicated status-history table**
   - Current state is stored on `inventory`; historical changes rely on `stock_movements`.

11. **Request and transaction meanings are overloaded**
   - The same fields have different meanings depending on the workflow, making reporting and maintenance more difficult.

12. **Documentation and schema naming differ**
   - Older documentation refers to `assignment_requests`, while the active table is `requests`.

The highest-priority gap is **dedicated maintenance records**, followed by the **maintenance completion workflow**.