import classNames from 'classnames';
import { t } from './format';

export function Pagination({ page, pages, onPage }: { page: number; pages: number; onPage: (page: number) => void }) {
  if (pages <= 1) return null;
  // Show the first and last page, and a window around the current one
  const numbers = Array.from({ length: pages }, (_, i) => i + 1)
    .filter(n => n === 1 || n === pages || Math.abs(n - page) <= 2);

  return (
    <nav aria-label={t('pagination')}>
      <ul className="pagination justify-content-center flex-wrap">
        <li className={classNames('page-item', { disabled: page === 1 })}>
          <button type="button" className="page-link" aria-label={t('previous')} disabled={page === 1} onClick={() => onPage(page - 1)}>‹</button>
        </li>
        {numbers.map((n, i) => [
          i > 0 && n - numbers[i - 1] > 1 && <li key={`gap-${n}`} className="page-item disabled"><span className="page-link">…</span></li>,
          <li key={n} className={classNames('page-item', { active: n === page })}>
            <button type="button" className="page-link" aria-current={n === page ? 'page' : undefined} onClick={() => onPage(n)}>{n}</button>
          </li>,
        ])}
        <li className={classNames('page-item', { disabled: page === pages })}>
          <button type="button" className="page-link" aria-label={t('next')} disabled={page === pages} onClick={() => onPage(page + 1)}>›</button>
        </li>
      </ul>
    </nav>
  );
}
