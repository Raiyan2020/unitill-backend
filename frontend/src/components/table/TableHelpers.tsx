import { Button } from '../ui/button';

type LoadingRowProps = {
  colSpan: number;
  label?: string;
};

export function TableLoadingRow({ colSpan, label = 'Loading data...' }: LoadingRowProps) {
  return (
    <tr>
      <td colSpan={colSpan} className="px-4 py-8">
        <div className="flex items-center justify-center gap-3 text-sm text-[#8a8da8]">
          <span className="h-4 w-4 animate-spin rounded-full border-2 border-[#7367f0]/30 border-t-[#7367f0]" />
          {label}
        </div>
      </td>
    </tr>
  );
}

type TableFooterProps = {
  page: number;
  pageSize: number;
  total: number;
  onPrev: () => void;
  onNext: () => void;
  prevDisabled: boolean;
  nextDisabled: boolean;
};

export function TableFooter({ page, pageSize, total, onPrev, onNext, prevDisabled, nextDisabled }: TableFooterProps) {
  const start = total === 0 ? 0 : (page - 1) * pageSize + 1;
  const end = Math.min(page * pageSize, total);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#ececf3] px-4 py-3 text-xs text-[#8a8da8] dark:border-[#44485f] dark:text-[#a2a5be]">
      <p>Showing {start} to {end} of {total} entries</p>
      <div className="flex gap-2">
        <Button variant="secondary" size="sm" disabled={prevDisabled} onClick={onPrev}>
          Prev
        </Button>
        <span className="inline-flex items-center px-2 text-sm">{page}</span>
        <Button variant="secondary" size="sm" disabled={nextDisabled} onClick={onNext}>
          Next
        </Button>
      </div>
    </div>
  );
}
