# Immutable duration baseline

Approved policy: a protected per-database file freezes SLOT_MINUTES before the first booking insert. Its directory is STATE_STORAGE_DIR (default storage/state), outside public/ and ignored by Git. It must be durable, backed up, and restricted to the service account (NTFS ACLs on Windows). Multiple app hosts must use the same shared durable directory. State identity uses MySQL server UUID plus database name; changing DB aliases cannot bypass it.

Booking, status changes, and slot reads acquire the same MySQL advisory mutex before transactions, then check the file under a filesystem lock. This deliberately serializes scheduling per database for safety. Existing doctor-row locks and interval revalidation remain in place. All application reservation writers must use this protocol. No schema changes, external service, or default clinic hours are introduced.

Once frozen, a different SLOT_MINUTES fails closed with 503, even if all appointments were cancelled or later removed. Existing reservations can still be completed/cancelled with booking disabled; restoring a cancelled reservation needs valid matching duration. Missing/corrupt state with existing appointments never infers a duration. Restore the original protected baseline from backup or design an explicit migration; there is no automatic override command.

The file is persisted before the insert/commit. A later DB failure may leave duration frozen despite no successful booking. This is conservative and intentional. Do not delete/edit the file to reconfigure scheduling, including after a failed attempt. A MySQL failover/server-UUID change or lost shared storage fails closed for existing appointments and requires deliberate recovery. Direct DB writers outside the application are unsupported.

Tests/duration-policy.php covers duration changes and adjacent/overlapping slots through tests/run.php. PHP/MySQL execution is pending runtime availability. Integration/HTTP tests must use fresh isolated _test databases and private temporary state directories, never production state.
