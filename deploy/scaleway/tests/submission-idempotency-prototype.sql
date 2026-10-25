-- Synthetic design proof only. Never apply to an application database.
CREATE TABLE fixture_submissions (
    id bigserial PRIMARY KEY,
    form_id bigint NOT NULL,
    payload jsonb NOT NULL
);
CREATE TABLE fixture_operations (
    form_id bigint NOT NULL,
    operation_key text NOT NULL,
    payload jsonb NOT NULL,
    submission_id bigint REFERENCES fixture_submissions(id),
    PRIMARY KEY (form_id, operation_key)
);
CREATE TABLE fixture_outbox (
    id bigserial PRIMARY KEY,
    form_id bigint NOT NULL,
    operation_key text NOT NULL,
    submission_id bigint NOT NULL REFERENCES fixture_submissions(id),
    state text NOT NULL DEFAULT 'pending' CHECK (state IN ('pending','claimed','ambiguous','delivered')),
    UNIQUE (form_id, operation_key)
);
CREATE TABLE fixture_external_effects (
    outbox_id bigint PRIMARY KEY REFERENCES fixture_outbox(id),
    calls integer NOT NULL
);

CREATE FUNCTION fixture_ingest(form bigint, operation text, body jsonb, fail_before_commit boolean DEFAULT false)
RETURNS bigint LANGUAGE plpgsql AS $$
DECLARE stored fixture_operations%ROWTYPE;
DECLARE created_id bigint;
BEGIN
    INSERT INTO fixture_operations(form_id,operation_key,payload)
        VALUES (form,operation,body) ON CONFLICT DO NOTHING;
    SELECT * INTO STRICT stored FROM fixture_operations
        WHERE form_id=form AND operation_key=operation FOR UPDATE;
    IF stored.payload <> body THEN
        RAISE EXCEPTION 'Operation key reused with a different payload';
    END IF;
    IF stored.submission_id IS NOT NULL THEN
        RETURN stored.submission_id;
    END IF;
    INSERT INTO fixture_submissions(form_id,payload) VALUES(form,body) RETURNING id INTO created_id;
    UPDATE fixture_operations SET submission_id=created_id
        WHERE form_id=form AND operation_key=operation;
    INSERT INTO fixture_outbox(form_id,operation_key,submission_id) VALUES(form,operation,created_id);
    IF fail_before_commit THEN
        RAISE EXCEPTION 'Injected crash before transaction commit';
    END IF;
    RETURN created_id;
END $$;
