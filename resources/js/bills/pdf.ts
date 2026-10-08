import * as pdfjs from 'pdfjs-dist/legacy/build/pdf';
import { itemsToText, TextItem } from './parseBill';

pdfjs.GlobalWorkerOptions.workerSrc = '/js/pdf.worker.min.js';

/** Extracts the text of every page. Runs entirely in the browser; the file never leaves it. */
export async function extractPdfText(data: ArrayBuffer): Promise<string> {
  // pdf.js transfers the buffer to its worker, so hand it a copy and keep the original for hashing
  const doc = await pdfjs.getDocument({ data: new Uint8Array(data.slice(0)) }).promise;
  try {
    const pages: string[] = [];
    for (let i = 1; i <= doc.numPages; i += 1) {
      // eslint-disable-next-line no-await-in-loop
      const page = await doc.getPage(i);
      // eslint-disable-next-line no-await-in-loop
      const content = await page.getTextContent();
      pages.push(itemsToText(content.items.filter(item => 'str' in item) as TextItem[]));
    }
    return pages.join('\n');
  } finally {
    await doc.destroy();
  }
}
