/**
 * Cloudflare Worker — OpenAI Chat Completions proxy for Azure East Asia.
 *
 * Why: api.openai.com returns 403 from some Azure regions ("Country, region,
 * or territory not supported"). This Worker runs on Cloudflare's network and
 * forwards the same Authorization + JSON body to OpenAI.
 *
 * Setup: https://dash.cloudflare.com → Workers & Pages → Create Worker
 * Paste this file → Deploy → copy URL like https://zpgc-openai-proxy.NAME.workers.dev
 *
 * Azure App Setting:
 *   OPENAI_API_BASE = https://zpgc-openai-proxy.NAME.workers.dev/v1
 *   (keep OPENAI_API_KEY on Azure; Worker only relays the Bearer token)
 */
export default {
  async fetch(request) {
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        status: 204,
        headers: {
          'Access-Control-Allow-Origin': '*',
          'Access-Control-Allow-Methods': 'POST, OPTIONS',
          'Access-Control-Allow-Headers': 'Authorization, Content-Type',
        },
      });
    }

    if (request.method !== 'POST') {
      return Response.json({ error: 'POST only' }, { status: 405 });
    }

    const auth = request.headers.get('Authorization') || '';
    if (!auth.toLowerCase().startsWith('bearer ')) {
      return Response.json({ error: 'Authorization Bearer required' }, { status: 401 });
    }

    const url = new URL(request.url);
    // Accept /v1/chat/completions or /chat/completions
    let path = url.pathname.replace(/\/+$/, '');
    if (path.endsWith('/chat/completions')) {
      path = '/v1/chat/completions';
    } else if (path === '' || path === '/') {
      path = '/v1/chat/completions';
    } else if (!path.startsWith('/v1/')) {
      path = '/v1' + (path.startsWith('/') ? path : '/' + path);
    }

    const upstream = await fetch('https://api.openai.com' + path, {
      method: 'POST',
      headers: {
        Authorization: auth,
        'Content-Type': 'application/json',
      },
      body: await request.text(),
    });

    const text = await upstream.text();
    return new Response(text, {
      status: upstream.status,
      headers: {
        'Content-Type': upstream.headers.get('Content-Type') || 'application/json',
        'Access-Control-Allow-Origin': '*',
      },
    });
  },
};
