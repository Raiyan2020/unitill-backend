<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRating;
use Illuminate\Database\Seeder;

class UserRatingSeeder extends Seeder
{
    public function run(): void
    {
        $ratedUser = User::query()->where('email', 'demo.user@unitill.local')->first()
            ?? User::query()->first();

        if (! $ratedUser) {
            return;
        }

        $reviewers = User::query()
            ->where('id', '!=', $ratedUser->id)
            ->limit(3)
            ->get();

        if ($reviewers->isEmpty()) {
            $reviewers = collect([
                User::query()->firstOrCreate(
                    ['email' => 'alex.reviewer@unitill.local'],
                    [
                        'name' => 'Alex Turner',
                        'first_name' => 'Alex',
                        'last_name' => 'Turner',
                        'phone' => '0590000001',
                        'status' => '1',
                        'password' => '123456',
                    ]
                ),
                User::query()->firstOrCreate(
                    ['email' => 'sarah.reviewer@unitill.local'],
                    [
                        'name' => 'Sarah Wilson',
                        'first_name' => 'Sarah',
                        'last_name' => 'Wilson',
                        'phone' => '0590000002',
                        'status' => '1',
                        'password' => '123456',
                    ]
                ),
            ]);
        }

        $samples = [
            [
                'score' => 5,
                'comment' => 'Very professional seller. The laptop was in perfect condition as described. Would definitely buy again!',
                'created_at' => now()->subDays(10),
            ],
            [
                'score' => 5,
                'comment' => 'Fast response and smooth transaction.',
                'created_at' => now()->subDays(20),
            ],
            [
                'score' => 4,
                'comment' => 'Good experience overall, item matched the description.',
                'created_at' => now()->subDays(35),
            ],
            [
                'score' => 3,
                'comment' => 'Average experience, delivery took longer than expected.',
                'created_at' => now()->subDays(50),
            ],
        ];

        foreach ($samples as $index => $sample) {
            $rater = $reviewers[$index % $reviewers->count()] ?? $reviewers->first();

            UserRating::query()->updateOrCreate(
                [
                    'rater_id' => $rater->id,
                    'rated_user_id' => $ratedUser->id,
                ],
                [
                    'score' => $sample['score'],
                    'comment' => $sample['comment'],
                    'created_at' => $sample['created_at'],
                    'updated_at' => $sample['created_at'],
                ]
            );
        }
    }
}
