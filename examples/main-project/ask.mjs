// مثال Node.js (Node 18+) لو عايز تنادي الخدمة من مشروع Node (مثل الـ middleware)
const res = await fetch(`${process.env.AI_STATS_URL}/api/v1/ask`, {
  method: 'POST',
  headers: {
    Authorization: `Bearer ${process.env.AI_STATS_TOKEN}`,
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  body: JSON.stringify({ question: 'كام شحنة NFC اتعملت الشهر ده؟', user_ref: 'admin-1' }),
  signal: AbortSignal.timeout(120_000),
});

if (!res.ok) throw new Error(`AI stats service error: ${res.status}`);

const { answer, tools, usage } = await res.json();
console.log(answer, tools, usage);
