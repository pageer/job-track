import { marked } from 'marked';

marked.use({ breaks: true, gfm: true });

export function renderMarkdown(src: string): string {
  const result = marked.parse(src);
  return typeof result === 'string' ? result : '';
}
