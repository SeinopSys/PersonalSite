const isPdf = (file: File) => /\.(pdf|jpe?g|png|webp)$/i.test(file.name) || file.type === 'application/pdf' || file.type.startsWith('image/');

const readAllEntries = (reader: FileSystemDirectoryReader): Promise<FileSystemEntry[]> => new Promise((resolve, reject) => {
  const all: FileSystemEntry[] = [];
  const next = () => reader.readEntries(batch => {
    if (batch.length === 0) resolve(all);
    else {
      all.push(...batch);
      next();
    }
  }, reject);
  next();
});

async function filesOf(entry: FileSystemEntry): Promise<File[]> {
  if (entry.isFile) {
    const file = await new Promise<File>((resolve, reject) => { (entry as FileSystemFileEntry).file(resolve, reject); });
    return isPdf(file) ? [file] : [];
  }
  const children = await readAllEntries((entry as FileSystemDirectoryEntry).createReader());
  return (await Promise.all(children.map(filesOf))).flat();
}

/**
 * Collects the files of a drop, descending into dropped folders (PDFs only). Entries have to be grabbed
 * synchronously inside the drop event, before anything is awaited.
 */
export async function filesFromDrop(dataTransfer: DataTransfer): Promise<File[]> {
  const entries = Array.from(dataTransfer.items ?? [])
    .map(item => (item.kind === 'file' ? item.webkitGetAsEntry() : null));
  const plain = Array.from(dataTransfer.files);
  if (entries.length === 0 || entries.some(e => e === null)) return plain;
  // Loose files are kept as they are so non-PDFs can be reported; folder contents are limited to PDFs
  const nested = await Promise.all(entries.map(async (entry, i) => (entry && entry.isDirectory ? filesOf(entry) : [plain[i]])));
  return nested.flat().filter(Boolean);
}

export const pdfsOnly = (files: File[]): File[] => files.filter(isPdf);
