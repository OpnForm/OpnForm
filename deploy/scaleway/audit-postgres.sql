\set ON_ERROR_STOP on
BEGIN READ ONLY;
SET LOCAL statement_timeout = '20s';
SET LOCAL lock_timeout = '2s';
SHOW transaction_read_only;
SELECT version(), current_database(), pg_database_size(current_database()) AS bytes;
SELECT datname, pg_encoding_to_char(encoding) AS encoding, datcollate, datctype
FROM pg_database WHERE datname = current_database();
SELECT extname, extversion FROM pg_extension ORDER BY extname;
SELECT schemaname, relname, n_live_tup, n_dead_tup,
       pg_total_relation_size(relid) AS bytes,
       last_autovacuum, last_autoanalyze
FROM pg_stat_user_tables ORDER BY bytes DESC;
SELECT schemaname, sequencename, data_type, start_value, min_value, max_value,
       increment_by, cycle, cache_size, last_value
FROM pg_sequences ORDER BY schemaname, sequencename;
SELECT application_name, state, count(*) AS connections
FROM pg_stat_activity WHERE datname = current_database()
GROUP BY application_name, state;
SELECT slot_name, slot_type, active FROM pg_replication_slots;
ROLLBACK;
