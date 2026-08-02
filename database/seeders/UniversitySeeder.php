<?php

namespace Database\Seeders;

use App\Models\University;
use Illuminate\Database\Seeder;

class UniversitySeeder extends Seeder
{
    public function run(): void
    {
        // name, region/nation, city, [domains/subdomains]
        $universities = [
            ['University of Oxford', 'England', 'Oxford', ['ox.ac.uk', 'stcatz.ox.ac.uk', 'cs.ox.ac.uk']],
            ['University of Cambridge', 'England', 'Cambridge', ['cam.ac.uk', 'cai.cam.ac.uk', 'trinity.cam.ac.uk']],
            ['Imperial College London', 'England', 'London', ['imperial.ac.uk', 'ic.ac.uk']],
            ['University College London', 'England', 'London', ['ucl.ac.uk']],
            ['London School of Economics and Political Science', 'England', 'London', ['lse.ac.uk']],
            ["King's College London", 'England', 'London', ['kcl.ac.uk']],
            ['University of Edinburgh', 'Scotland', 'Edinburgh', ['ed.ac.uk', 'sms.ed.ac.uk']],
            ['University of Manchester', 'England', 'Manchester', ['manchester.ac.uk', 'student.manchester.ac.uk']],
            ['University of Bristol', 'England', 'Bristol', ['bristol.ac.uk']],
            ['University of Warwick', 'England', 'Coventry', ['warwick.ac.uk']],
            ['University of Glasgow', 'Scotland', 'Glasgow', ['glasgow.ac.uk', 'student.gla.ac.uk']],
            ['University of Birmingham', 'England', 'Birmingham', ['bham.ac.uk']],
            ['University of Leeds', 'England', 'Leeds', ['leeds.ac.uk']],
            ['University of Sheffield', 'England', 'Sheffield', ['sheffield.ac.uk']],
            ['University of Nottingham', 'England', 'Nottingham', ['nottingham.ac.uk']],
            ['University of Southampton', 'England', 'Southampton', ['southampton.ac.uk', 'soton.ac.uk']],
            ['Durham University', 'England', 'Durham', ['durham.ac.uk', 'dur.ac.uk']],
            ['University of St Andrews', 'Scotland', 'St Andrews', ['st-andrews.ac.uk']],
            ['University of York', 'England', 'York', ['york.ac.uk']],
            ['Lancaster University', 'England', 'Lancaster', ['lancaster.ac.uk']],
            ['University of Exeter', 'England', 'Exeter', ['exeter.ac.uk']],
            ['University of Bath', 'England', 'Bath', ['bath.ac.uk']],
            ['Queen Mary University of London', 'England', 'London', ['qmul.ac.uk']],
            ['Cardiff University', 'Wales', 'Cardiff', ['cardiff.ac.uk']],
            ['University of Liverpool', 'England', 'Liverpool', ['liverpool.ac.uk', 'liv.ac.uk']],
            ['Newcastle University', 'England', 'Newcastle upon Tyne', ['newcastle.ac.uk', 'ncl.ac.uk']],
            ['University of Aberdeen', 'Scotland', 'Aberdeen', ['abdn.ac.uk']],
            ["Queen's University Belfast", 'Northern Ireland', 'Belfast', ['qub.ac.uk']],
            ['University of Reading', 'England', 'Reading', ['reading.ac.uk']],
            ['University of Sussex', 'England', 'Brighton', ['sussex.ac.uk']],

            // Present in the Salman allowlist but absent here. Registration
            // validates the student email against this table, so while these
            // were missing every student at these eleven universities was
            // rejected at sign-up.
            ['University of Leicester', 'England', 'Leicester', ['leicester.ac.uk']],
            ['Loughborough University', 'England', 'Loughborough', ['lboro.ac.uk']],
            ['University of Surrey', 'England', 'Guildford', ['surrey.ac.uk']],
            ['Aston University', 'England', 'Birmingham', ['aston.ac.uk']],
            ['Coventry University', 'England', 'Coventry', ['coventry.ac.uk']],
            ['Leeds Beckett University', 'England', 'Leeds', ['leedsbeckett.ac.uk']],
            ['Manchester Metropolitan University', 'England', 'Manchester', ['mmu.ac.uk']],
            ['Northumbria University', 'England', 'Newcastle upon Tyne', ['northumbria.ac.uk']],
            ['University of Portsmouth', 'England', 'Portsmouth', ['port.ac.uk']],
            ['University of Strathclyde', 'Scotland', 'Glasgow', ['strath.ac.uk']],
            ['University of Dundee', 'Scotland', 'Dundee', ['dundee.ac.uk']],
        ];

        foreach ($universities as $index => [$name, $region, $city, $domains]) {
            $university = University::updateOrCreate(
                ['name' => $name],
                [
                    'country_code' => 'GB',
                    'state' => $region,
                    'city' => $city,
                    'status' => 'active',
                    'sort' => $index,
                ]
            );

            foreach ($domains as $domain) {
                $university->domains()->updateOrCreate(
                    ['domain' => strtolower($domain)],
                    ['status' => 'active']
                );
            }
        }
    }
}
