<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseSemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('--- CourseSemesterSeeder is starting ---');

        // First, let's populate from pharmacy_degree_plan.CSV
        $this->populateFromPharmacyDegreePlan();
        
        // Then, populate from university_requirements.CSV
        $this->populateFromUniversityRequirements();

        Log::info('--- CourseSemesterSeeder has finished ---');
    }

    private function populateFromPharmacyDegreePlan()
    {
        $csvFile = database_path('data/pharmacy_degree_plan.CSV');
        
        if (!file_exists($csvFile)) {
            Log::warning("Pharmacy degree plan CSV file not found: {$csvFile}");
            return;
        }

        $file = fopen($csvFile, 'r');
        if (!$file) {
            Log::error("Could not open pharmacy degree plan CSV file: {$csvFile}");
            return;
        }

        // Skip header row
        fgetcsv($file);

        $rowCount = 0;
        $insertedCount = 0;
        
        while (($data = fgetcsv($file, 2000, ',')) !== false) {
            $rowCount++;
            
            // Log progress every 100 rows
            if ($rowCount % 100 === 0) {
                Log::info("Processed {$rowCount} rows from pharmacy degree plan CSV");
            }
            
            if (count($data) < 8) {
                Log::warning("Row {$rowCount}: Insufficient columns in pharmacy degree plan CSV");
                continue;
            }

            $courseCode = trim($data[0]); // Column A: Course Code
            $year = trim($data[4]);       // Column E: Year
            $semester = trim($data[5]);   // Column F: Semester

            if (empty($courseCode) || empty($year) || empty($semester)) {
                continue;
            }

            // Find the course
            $course = DB::table('courses')->where('course_number', $courseCode)->first();
            if (!$course) {
                Log::warning("Course not found: {$courseCode}");
                continue;
            }

            // Find the semester (1=Fall, 2=Spring, 3=Summer)
            $semesterName = match((int)$semester) {
                1 => 'Fall',
                2 => 'Spring', 
                3 => 'Summer',
                default => 'Fall'
            };
            
            $semesterRecord = DB::table('semesters')
                ->where('Year', $year)
                ->where('SemesterName', $semesterName)
                ->first();

            if (!$semesterRecord) {
                Log::warning("Semester not found: Year {$year}, Semester {$semester}");
                continue;
            }

            // Insert course_semester relationship if it doesn't exist
            $exists = DB::table('course_semesters')
                ->where('course_id', $course->id)
                ->where('semester_id', $semesterRecord->id)
                ->exists();

            if (!$exists) {
                try {
                    DB::table('course_semesters')->insert([
                        'course_id' => $course->id,
                        'semester_id' => $semesterRecord->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    $insertedCount++;
                    Log::info("Linked course {$courseCode} to semester {$year}-{$semester}");
                } catch (\Exception $e) {
                    Log::warning("Failed to link course {$courseCode} to semester {$year}-{$semester}: " . $e->getMessage());
                }
            }
        }

        fclose($file);
        Log::info("Total course-semester links inserted: {$insertedCount}");
    }

    private function populateFromUniversityRequirements()
    {
        $csvFile = database_path('data/university_requirements.CSV');
        
        if (!file_exists($csvFile)) {
            Log::warning("University requirements CSV file not found: {$csvFile}");
            return;
        }

        $file = fopen($csvFile, 'r');
        if (!$file) {
            Log::error("Could not open university requirements CSV file: {$csvFile}");
            return;
        }

        // Skip header row
        fgetcsv($file);

        $rowCount = 0;
        $insertedCount = 0;
        while (($data = fgetcsv($file, 2000, ',')) !== false) {
            $rowCount++;
            
            if (count($data) < 7) {
                Log::warning("Row {$rowCount}: Insufficient columns in university requirements CSV");
                continue;
            }

            $courseCode = trim($data[0]); // Column A: Course Code
            $semester = trim($data[4]);   // Column E: Semester
            $year = trim($data[5]);       // Column F: Year

            if (empty($courseCode) || empty($year) || empty($semester)) {
                continue;
            }

            // Find the course
            $course = DB::table('courses')->where('course_number', $courseCode)->first();
            if (!$course) {
                Log::warning("Course not found: {$courseCode}");
                continue;
            }

            // Find the semester (1=Fall, 2=Spring, 3=Summer)
            $semesterName = match((int)$semester) {
                1 => 'Fall',
                2 => 'Spring', 
                3 => 'Summer',
                default => 'Fall'
            };
            
            $semesterRecord = DB::table('semesters')
                ->where('Year', $year)
                ->where('SemesterName', $semesterName)
                ->first();

            if (!$semesterRecord) {
                Log::warning("Semester not found: Year {$year}, Semester {$semester}");
                continue;
            }

            // Insert course_semester relationship if it doesn't exist
            $exists = DB::table('course_semesters')
                ->where('course_id', $course->id)
                ->where('semester_id', $semesterRecord->id)
                ->exists();

            if (!$exists) {
                try {
                    DB::table('course_semesters')->insert([
                        'course_id' => $course->id,
                        'semester_id' => $semesterRecord->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    $insertedCount++;
                    Log::info("Linked course {$courseCode} to semester {$year}-{$semester}");
                } catch (\Exception $e) {
                    Log::warning("Failed to link course {$courseCode} to semester {$year}-{$semester}: " . $e->getMessage());
                }
            }
        }

        fclose($file);
        Log::info("Total course-semester links inserted: {$insertedCount}");
    }
}
