-- Seed default agents for Docker MySQL image
-- Provides initial chat and responses agents used by the application

INSERT INTO agents (
    id,
    name,
    api_type,
    model,
    system_message,
    tools_json,
    vector_store_ids_json,
    is_default,
    created_at,
    updated_at
) VALUES (
    UUID(),
    'Default Chat Agent',
    'chat',
    'gpt-4o-mini',
    'You are the default chat assistant for live conversations.',
    '[]',
    '[]',
    1,
    NOW(),
    NOW()
);

INSERT INTO agents (
    id,
    name,
    api_type,
    model,
    system_message,
    tools_json,
    vector_store_ids_json,
    is_default,
    created_at,
    updated_at
) VALUES (
    UUID(),
    'File Search Responses Agent',
    'responses',
    'gpt-4o-mini',
    'Use the file search tool to answer questions about uploaded documents.',
    '[{"type":"file_search","vector_store_ids":["vs_documents"]}]',
    '["vs_documents"]',
    0,
    NOW(),
    NOW()
);

