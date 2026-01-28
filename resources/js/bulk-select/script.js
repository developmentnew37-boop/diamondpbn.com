import { initMultiCheckbox } from '../general'

window.addEventListener('DOMContentLoaded', () => {
   

    initMultiCheckbox({
        bulkSelector: 'bulk-checkBox-selector',
        itemClass: 'multi-check',
        hiddenInput: 'valHolders'
    });
})

// basic structure

//   initMultiCheckbox({
//         bulkSelector: 'bulk1',
//         itemClass: 'multi1',
//         hiddenInput: 'hidden1',
//         onChange: (selected) => {
//             console.log("Selected:", selected);
//         }
//     });