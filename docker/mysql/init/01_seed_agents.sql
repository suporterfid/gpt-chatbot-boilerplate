-- Seed default agents for Docker MySQL image
-- Provides initial chat and responses agents used by the application

INSERT INTO agents (
    id,
    name,
    description,
    api_type,
    model,
    prompt_id,
    prompt_version,
    system_message,
    temperature,
    top_p,
    max_output_tokens,
    tools_json,
    vector_store_ids_json,
    max_num_results,
    response_format_json,
    is_default,
    created_at,
    updated_at
) VALUES (
    UUID(),
    'Chat Agent',
    'A friendly conversational assistant ready to help with general questions and live chats.',
    'chat',
    'gpt-4o-mini',
    'prompt_chat_agent',
    '1',
    'You are a warm and engaging chat assistant. Greet users enthusiastically, ask helpful follow-up questions, and keep replies concise yet friendly.',
    1.0,
    1.0,
    1024,
    '[]',
    '[]',
    NULL,
    '{"type":"text"}',
    1,
    NOW(),
    NOW()
);

INSERT INTO agents (
    id,
    name,
    description,
    api_type,
    model,
    prompt_id,
    prompt_version,
    system_message,
    temperature,
    top_p,
    max_output_tokens,
    tools_json,
    vector_store_ids_json,
    max_num_results,
    response_format_json,
    is_default,
    created_at,
    updated_at
) VALUES (
    UUID(),
    'File Search Assistant',
    'Leverages the file search tool to ground answers in indexed documents and uploaded files.',
    'responses',
    'gpt-4o',
    'prompt_file_search_assistant',
    '1',
    'You are a file search specialist. When helpful, call the file_search tool to retrieve the most relevant documents before responding.',
    0.7,
    1.0,
    1024,
    '[{"type":"file_search"}]',
    '[]', -- Vector stores are selected dynamically per deployment.
    5,
    '{"type":"text"}',
    0,
    NOW(),
    NOW()
);

