import { useEffect, useState } from 'preact/hooks';
import { Dialog } from '../dialog';
import { api } from './api';
import { formatDate, t } from './format';

interface ShareLinkData {
  id: string;
  label: string | null;
  url: string;
  expires_at: string | null;
  expired: boolean;
  view_count: number;
  last_viewed_at: string | null;
  created_at: string;
}

interface ListResponse { status: boolean; links: ShareLinkData[] }
interface CreateResponse { status: boolean; link: ShareLinkData }

const EXPIRY_OPTIONS = [7, 30, 90, 365];

/** Public read-only links to the bills overview for the landlord, with discrepancies marked. */
export function Shares() {
  const [links, setLinks] = useState<ShareLinkData[] | null>(null);
  const [label, setLabel] = useState('');
  const [days, setDays] = useState('');
  const [busy, setBusy] = useState(false);
  const [copied, setCopied] = useState<string | null>(null);

  const load = async () => {
    const response = await api<ListResponse>('GET', '/bills/shares');
    if (response) setLinks(response.links);
  };

  useEffect(() => { load(); }, []);

  const create = async () => {
    setBusy(true);
    const response = await api<CreateResponse>('POST', '/bills/shares', {
      label: label.trim() || null,
      expires_in_days: days ? Number(days) : null,
    });
    setBusy(false);
    if (!response) return;
    setLabel('');
    await load();
  };

  const copy = async (link: ShareLinkData) => {
    try {
      await navigator.clipboard.writeText(link.url);
      setCopied(link.id);
      window.setTimeout(() => setCopied(current => (current === link.id ? null : current)), 2000);
    } catch {
      // Clipboard access can be blocked; the link is also shown in a field to copy by hand
    }
  };

  const revoke = (link: ShareLinkData) => Dialog.confirm({
    title: window.Laravel.dialog.confirm,
    content: t('share-confirm-revoke'),
    handlerFunc: async sure => {
      if (!sure) return;
      Dialog.close();
      if (await api('DELETE', `/bills/shares/${link.id}`)) await load();
    },
  });

  return (
    <section className="mb-4">
      <h3>{t('share-title')}</h3>
      <p className="text-body-secondary">{t('share-help')}</p>

      <div className="row g-2 align-items-end mb-3">
        <div className="col-sm-5">
          <label className="form-label" htmlFor="share-label">{t('share-label')}</label>
          <input id="share-label" type="text" maxLength={100} className="form-control" value={label} onChange={e => setLabel(e.currentTarget.value)} />
        </div>
        <div className="col-sm-3">
          <label className="form-label" htmlFor="share-expiry">{t('share-expiry')}</label>
          <select id="share-expiry" className="form-select" value={days} onChange={e => setDays(e.currentTarget.value)}>
            <option value="">{t('share-never')}</option>
            {EXPIRY_OPTIONS.map(n => <option key={n} value={n}>{t('share-days', { count: n })}</option>)}
          </select>
        </div>
        <div className="col-sm-auto">
          <button type="button" className="btn btn-primary" disabled={busy} onClick={create}>{t('share-create')}</button>
        </div>
      </div>

      {links === null ? <p>{t('loading')}</p> : links.length === 0 ? <p className="text-body-secondary">{t('share-none')}</p> : (
        <div className="list-group">
          {links.map(link => (
            <div key={link.id} className="list-group-item">
              <div className="d-flex justify-content-between flex-wrap gap-2">
                <strong>{link.label || '—'}</strong>
                <span className="text-body-secondary small">
                  {[
                    t('share-created', { date: formatDate(link.created_at) }),
                    link.expired ? t('share-expired') : link.expires_at ? t('share-expires', { date: formatDate(link.expires_at) }) : null,
                    t('share-views', { count: link.view_count }),
                    link.last_viewed_at ? t('share-last-viewed', { date: formatDate(link.last_viewed_at) }) : null,
                  ].filter(Boolean).join(' · ')}
                </span>
              </div>
              <div className="input-group input-group-sm my-2">
                <input type="text" readOnly className="form-control" aria-label={t('share-title')} value={link.url} onFocus={e => e.currentTarget.select()} />
                <button type="button" className="btn btn-outline-secondary" onClick={() => copy(link)}>{copied === link.id ? t('share-copied') : t('share-copy')}</button>
                <a className="btn btn-outline-secondary" href={link.url} target="_blank" rel="noopener noreferrer">{t('share-open')}</a>
              </div>
              <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => revoke(link)}>{t('share-revoke')}</button>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}
