/** Brand metadata for AI provider identity — icon tiles and card watermarks on the AI Settings page. */

export const AI_PROVIDER_META = {
  openai: {
    label: 'OpenAI',
    icon: 'openai',
    tileFg: '#0B0B0F',
  },
  gemini: {
    label: 'Google Gemini',
    icon: 'google',
    tileFg: '#1F2937',
  },
  flux: {
    label: 'Black Forest Labs',
    icon: 'flux',
    tileFg: '#0B0B0F',
  },
  grok: {
    label: 'xAI Grok',
    icon: 'grok',
    tileFg: '#0A0A0A',
  },
  groq: {
    label: 'Groq',
    letter: 'G',
    tileFg: '#F55036',
  },
  ollama: {
    label: 'Ollama',
    icon: 'ollama',
    tileFg: '#1F1F1F',
    tileBg: '#F5F1E8',
    tileBorder: '1px solid #E4DEC9',
  },
  stub: {
    label: 'Offline placeholder',
    letter: '–',
    tileFg: '#94A3B8',
  },
};

const FALLBACK = AI_PROVIDER_META.stub;

export function getAiProviderMeta(id) {
  return AI_PROVIDER_META[id] || FALLBACK;
}
