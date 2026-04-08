import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { useI18n } from '../providers/i18n-provider';

const miniBars = [44, 62, 37, 72, 55, 81, 67];

export function DashboardPage() {
  const { t } = useI18n();

  return (
    <div className="space-y-5">
      <div>
        <h2 className="text-xl font-semibold">{t.dashboard}</h2>
      </div>

      <div className="grid gap-4 xl:grid-cols-5 md:grid-cols-2">
        <MetricCard title="Order" value="124k" change="+12.6%" positive />
        <MetricCard title="Sales" value="175k" change="-16.2%" />
        <MetricCard title="Total Profit" value="1.28k" change="-12.2%" />
        <MetricCard title="Total Sales" value="24.67k" change="+24.5%" positive />

        <Card className="xl:col-span-1 md:col-span-2">
          <CardHeader>
            <CardTitle className="text-sm">Revenue Growth</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-3xl font-bold">$4,673</p>
            <div className="mt-4 flex items-end gap-2">
              {miniBars.map((h, idx) => (
                <span
                  key={idx}
                  className={`w-2 rounded-full ${idx === 4 ? 'bg-[#28c76f]' : 'bg-[#d6d8e5] dark:bg-[#4c516a]'}`}
                  style={{ height: `${h}px` }}
                />
              ))}
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle className="text-sm">Earning Reports</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="mb-4 flex flex-wrap gap-2">
              {['Orders', 'Sales', 'Profit', 'Income'].map((tab, i) => (
                <button
                  key={tab}
                  className={`rounded-lg px-4 py-2 text-sm ${i === 0 ? 'bg-[#7367f0] text-white' : 'bg-[#f4f5fa] text-[#6f6b7d] dark:bg-[#383d56] dark:text-[#b8bace]'}`}
                >
                  {tab}
                </button>
              ))}
            </div>
            <div className="flex h-48 items-end gap-3">
              {[28, 45, 38, 15, 30, 35, 30, 8].map((v, idx) => (
                <div key={idx} className="flex-1 rounded-t-md bg-[#7367f0]/80 dark:bg-[#7367f0]" style={{ height: `${v * 3}px` }} />
              ))}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="text-sm">Sales</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="mx-auto mt-4 h-52 w-52 rounded-full border-[18px] border-[#7367f0]/70 border-e-[#00cfe8] border-b-[#00cfe8] dark:border-[#7367f0] dark:border-e-[#00cfe8] dark:border-b-[#00cfe8]" />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function MetricCard({ title, value, change, positive = false }: { title: string; value: string; change: string; positive?: boolean }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm text-[#6f6b7d] dark:text-[#b6b8cc]">{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-3xl font-bold text-[#2f2b3d] dark:text-[#d7d8ea]">{value}</p>
        <p className={`mt-2 text-xs ${positive ? 'text-[#28c76f]' : 'text-[#ea5455]'}`}>{change}</p>
      </CardContent>
    </Card>
  );
}
