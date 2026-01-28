export function allowOnlyNumbers(numInp) {
    if (numInp.length) {
        Array.from(numInp).forEach((i) => {
            i.addEventListener("input", function () {
                this.value = this.value.replace(/[^0-9]/g, "");
            });
        });
    }
}