Create an ITB PDF template that matches the approved layout, including the item table, clauses, signatories, and footer.

Add a backend endpoint to validate the selected disposal-eligible items and ITB details, then generate the PDF using the project’s existing PDF library.


Connect the preview to that endpoint, sending the selected items and current form values.

Show the generated PDF inline in a modal or preview panel, with loading and error states.

Add actions to print or download the PDF, and return to editing without losing the entered details.

Verify the PDF preview with different item counts and page breaks; confirm the printed/downloaded PDF matches what the preview shows.