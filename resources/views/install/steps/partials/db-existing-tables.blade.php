    <div class="alert alert-warning mt-4 mb-0">
        <p class="mb-2 fw-semibold">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Existing database detected
        </p>
        <p class="small mb-3">The database already contains tables. Choose how to proceed:</p>
        <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-danger btn-sm"
                    @click="action = 'fresh'">
                <i class="bi bi-trash me-1"></i>
                Fresh Install
                <span class="badge bg-white text-danger ms-1" style="font-size:.65rem">drops all data</span>
            </button>
            <button type="submit" class="btn btn-outline-secondary btn-sm"
                    @click="action = 'sync'">
                <i class="bi bi-arrow-up-circle me-1"></i>
                Migrate over existing
                <span class="badge bg-secondary ms-1" style="font-size:.65rem">keeps data</span>
            </button>
        </div>
    </div>
