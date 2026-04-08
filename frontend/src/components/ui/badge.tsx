import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '../../lib/cn';

const badgeVariants = cva('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', {
  variants: {
    variant: {
      default: 'bg-[#7367f0] text-white',
      success: 'bg-[#28c76f1f] text-[#28c76f] dark:bg-[#28c76f29] dark:text-[#63f29f]',
      warning: 'bg-[#ff9f431f] text-[#ff9f43] dark:bg-[#ff9f4326] dark:text-[#ffbf7b]',
      destructive: 'bg-[#ea54551f] text-[#ea5455] dark:bg-[#ea545529] dark:text-[#ff8f8f]',
    },
  },
  defaultVariants: {
    variant: 'default',
  },
});

export interface BadgeProps extends React.HTMLAttributes<HTMLDivElement>, VariantProps<typeof badgeVariants> {}

export function Badge({ className, variant, ...props }: BadgeProps) {
  return <div className={cn(badgeVariants({ variant }), className)} {...props} />;
}
