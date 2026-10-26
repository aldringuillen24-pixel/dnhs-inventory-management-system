# ITB Component Extraction Plan

## Goal

Move the ITB selection and preview workflow out of `Inventory.vue` into a dedicated component under `resources/js/spa/views/custodian/components/`, following the existing print-component pattern. Preserve the current UI and behavior.

## Work breakdown

1. **Map the existing ITB workflow**
   - Identify the ITB entry point in `Inventory.vue`, its modal(s), the category-selection drawer, HTML and PDF preview modes, and the related state, watchers, API requests, and handlers.
   - Separate ITB-only logic from inventory-page state and shared inventory utilities.
   - Account for both visible preview markup and hidden measurement markup used for page pagination.

2. **Define the component interface**
   - Decide which inventory data or callbacks the component needs from `Inventory.vue`.
   - Define component events for opening/closing and any results that must return to the parent.
   - Keep item selection and editable ITB metadata in the component unless the existing parent needs to own a value.
   - Preserve the current selection eligibility rules and the generated-PDF request contract.

3. **Extract the ITB template**
   - Move the ITB selection and preview UI into a dedicated component.
   - Keep the drawer toggle, eligible-item list, confirmation/review steps, preview controls, editable fields, pagination, print/download controls, and error/loading states.
   - Preserve the measurement template and synchronize it with the visible HTML preview so pagination remains accurate.

4. **Extract and reconnect ITB behavior**
   - Move ITB-specific reactive state, loading, selection, PDF preview, pagination, printing, downloading, and cleanup logic.
   - Reconnect the component to the existing API endpoints and ensure edits invalidate or refresh the PDF preview as they do now.
   - Ensure closing/reopening the modal and switching preview modes retain the current expected reset and refresh behavior.

5. **Integrate with `Inventory.vue`**
   - Import and render the new ITB component alongside the existing IIRUP and RFQ components.
   - Keep the inventory page responsible for triggering the ITB workflow, without retaining duplicate ITB modal markup or ITB-only logic.
   - Remove only the extracted ITB code and imports/state; leave unrelated inventory features unchanged.

6. **Verify behavior**
   - Run focused ITB PDF feature tests and relevant PHP/Blade checks.
   - Run the available frontend validation that does not invoke `npm build` unless explicitly requested.
   - Verify the drawer, category and item selection, review/confirmation, editable metadata, HTML pagination, PDF preview refresh, print, download, error handling, and close/reopen flow.
   - Check the final diff for unrelated changes and whitespace issues.

## Completion criteria

- `Inventory.vue` no longer contains the ITB modal, preview templates, or ITB-specific workflow logic.
- The new ITB component preserves existing behavior and remains wired to the current PDF endpoint.
- The HTML preview and its pagination measurement markup stay consistent.
- Focused tests and available validation pass without running `npm build`.