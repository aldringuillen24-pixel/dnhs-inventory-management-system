Pagination steps:

1. Decide the page size, such as 25 or 50 inventory groups per page.

2. Add server-side filters:
   - status;
   - search text;
   - category;
   - disposed or non-disposed state.

3. Update the inventory query to return paginated results instead of loading everything.

4. Keep inventory grouping in the database before pagination so related quantities remain correct.

5. Update the controller to pass pagination and filter values to the view.

6. Add pagination controls:
   - Previous;
   - Next;
   - page numbers;
   - total results.

7. Preserve filter values when changing pages.

8. Update the Alpine.js interface so changing tabs submits or reloads the correct server-side filter.

9. Optimize related records so each paginated item loads only:
   - latest maintenance record;
   - latest stock movement;
   - latest disposal movement.

10. Add tests for:
   - page navigation;
   - search across pages;
   - category filtering;
   - status tabs;
   - disposed inventory;
   - empty results;
   - filter values preserved between pages.

11. Run the focused inventory tests, then the complete suite.

The main design decision is whether changing a tab reloads the page or uses AJAX. A normal page reload is simpler and more reliable for this Laravel Blade application.