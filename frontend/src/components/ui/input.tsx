import { cn } from '../../lib/cn';

export function Input({ className, ...props }: React.InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      className={cn(
        'flex h-10 w-full rounded-xl border border-[#dbdbe8] bg-white px-3 py-2 text-sm text-[#2f2b3d] placeholder:text-[#9c9cb0] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 dark:border-[#4a4f68] dark:bg-[#2f3349] dark:text-[#d7d8ea] dark:placeholder:text-[#8f93ad] disabled:cursor-not-allowed disabled:opacity-50',
        className,
      )}
      {...props}
    />
  );
}
