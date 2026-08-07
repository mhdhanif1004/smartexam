export function selectionManager(config) {
    return {
        sessionKey: config.sessionKey,
        selected: [],
        visibleIds: config.visibleIds || [],
        bulkDeleteSuccess: config.bulkDeleteSuccess ?? false,
        bulkDeleteUrl: config.bulkDeleteUrl || '',
        init() {
            if (this.bulkDeleteSuccess) {
                this.clearSelection();
            } else {
                this.selected = this.loadSelection();
            }
        },
        loadSelection() {
            try {
                const parsed = JSON.parse(window.sessionStorage.getItem(this.sessionKey) || '[]');
                return Array.isArray(parsed)
                    ? [...new Set(parsed.filter((id) => Number.isInteger(id)))]
                    : [];
            } catch (e) {
                return [];
            }
        },
        persistSelection() {
            try {
                window.sessionStorage.setItem(this.sessionKey, JSON.stringify(this.selected));
            } catch (e) {
                // Penyimpanan tidak tersedia (mis. mode privat); abaikan.
            }
        },
        clearSelection() {
            this.selected = [];
            try {
                window.sessionStorage.removeItem(this.sessionKey);
            } catch (e) {
                // Penyimpanan tidak tersedia; abaikan.
            }
        },
        toggleSelect(id) {
            const index = this.selected.indexOf(id);
            if (index === -1) {
                this.selected.push(id);
            } else {
                this.selected.splice(index, 1);
            }
            this.persistSelection();
        },
        selectAll() {
            const allVisibleSelected = this.visibleIds.length > 0
                && this.visibleIds.every((id) => this.selected.includes(id));
            if (allVisibleSelected) {
                this.selected = this.selected.filter((id) => !this.visibleIds.includes(id));
            } else {
                this.visibleIds.forEach((id) => {
                    if (!this.selected.includes(id)) {
                        this.selected.push(id);
                    }
                });
            }
            this.persistSelection();
        },
    };
}
