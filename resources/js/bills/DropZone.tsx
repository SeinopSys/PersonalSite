import { useRef, useState } from 'preact/hooks';
import classNames from 'classnames';
import { filesFromDrop, pdfsOnly } from './dropFiles';
import { BILL_TYPES, BillType } from './types';
import { t } from './format';

interface DropZoneProps {
  type: BillType;
  busy: string | null;
  onTypeChange: (type: BillType) => void;
  pending: File[];
  onFiles: (files: File[]) => void;
  onRemove: (index: number) => void;
  onClear: () => void;
  onAnalyze: () => void;
  onManual: () => void;
}

export function DropZone({
  type, busy, onTypeChange, pending, onFiles, onRemove, onClear, onAnalyze, onManual,
}: DropZoneProps) {
  const [over, setOver] = useState(false);
  const folderInput = useRef<HTMLInputElement>(null);

  return (
    <div className="mb-4">
      <div className="row g-2 mb-2 align-items-end">
        <div className="col-sm-4">
          <label htmlFor="bill-type" className="form-label">{t('bill-type')}</label>
          <select id="bill-type" className="form-select" value={type} onChange={e => onTypeChange(e.currentTarget.value as BillType)}>
            {BILL_TYPES.map(item => <option key={item} value={item}>{t(`type-${item}`)}</option>)}
          </select>
        </div>
        <div className="col-sm-auto">
          <button type="button" className="btn btn-outline-secondary me-2" onClick={() => folderInput.current?.click()}>{t('choose-folder')}</button>
          <input
            ref={folderInput}
            type="file"
            hidden
            // eslint-disable-next-line react/jsx-props-no-spreading
            {...{ webkitdirectory: true }}
            onChange={e => {
              onFiles(pdfsOnly(Array.from(e.currentTarget.files ?? [])));
              e.currentTarget.value = '';
            }}
          />
          <button type="button" className="btn btn-outline-secondary" onClick={onManual}>{t('add-manual')}</button>
        </div>
      </div>
      {/* eslint-disable-next-line jsx-a11y/label-has-associated-control */}
      <label
        className={classNames('bills-dropzone', { over })}
        onDragOver={e => { e.preventDefault(); setOver(true); }}
        onDragLeave={() => setOver(false)}
        onDrop={e => {
          e.preventDefault();
          setOver(false);
          if (e.dataTransfer) filesFromDrop(e.dataTransfer).then(onFiles);
        }}
      >
        <input
          type="file"
          accept="application/pdf,.pdf,image/*"
          multiple
          hidden
          onChange={e => {
            onFiles(Array.from(e.currentTarget.files ?? []));
            e.currentTarget.value = '';
          }}
        />
        <span>{busy ?? t('drop-label')}</span>
      </label>
      {pending.length > 0 && (
        <div className="mt-2">
          <div className="d-flex gap-2 align-items-center flex-wrap mb-2">
            <button type="button" className="btn btn-primary" disabled={busy !== null} onClick={onAnalyze}>
              {t('analyze')}
              {` (${pending.length})`}
            </button>
            <button type="button" className="btn btn-secondary" disabled={busy !== null} onClick={onClear}>{t('clear-queue')}</button>
            <span className="text-body-secondary small">{t('analyze-as', { type: t(`type-${type}`) })}</span>
          </div>
          <ul className="list-group bills-queue">
            {pending.map((file, index) => (
              <li key={`${file.name}-${file.size}-${file.lastModified}`} className="list-group-item d-flex justify-content-between align-items-center py-1">
                <span className="text-break">{file.name}</span>
                <button type="button" className="btn-close" aria-label={t('remove')} disabled={busy !== null} onClick={() => onRemove(index)} />
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
