// PDF download functionality using browser's print-to-PDF
function downloadPDF(filename) {
    // Store original title
    const originalTitle = document.title;

    // Set document title for PDF filename
    document.title = filename;

    // Trigger print dialog
    window.print();

    // Restore original title after a short delay
    setTimeout(() => {
        document.title = originalTitle;
    }, 1000);
}

// Initialize PDF download buttons
document.addEventListener('DOMContentLoaded', function() {
    const pdfButtons = document.querySelectorAll('.btn-pdf');
    pdfButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const filename = this.getAttribute('data-pdf-filename') || 'document';
            downloadPDF(filename);
        });
    });
});
