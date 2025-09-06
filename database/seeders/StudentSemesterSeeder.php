<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentSemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('--- StudentSemesterSeeder is starting ---');

        $csvFile = database_path('data/pharmacy_student_records.CSV');
        
        if (!file_exists($csvFile)) {
            Log::warning("Pharmacy student records CSV file not found: {$csvFile}");
            return;
        }

        $file = fopen($csvFile, 'r');
        if (!$file) {
            Log::error("Could not open pharmacy student records CSV file: {$csvFile}");
            return;
        }

        // Skip header row
        fgetcsv($file);

        $studentSemesters = [];
        $processedStudents = [];

        $rowCount = 0;
        while (($data = fgetcsv($file, 2000, ',')) !== false) {
            $rowCount++;
            
            if (count($data) < 8) {
                Log::warning("Row {$rowCount}: Insufficient columns in student records CSV");
                continue;
            }

            $studentId = trim($data[1]); // Column B: Student ID
            $yearCode = trim($data[2]);  // Column C: Year code (e.g., 20181)

            if (empty($studentId) || empty($yearCode)) {
                continue;
            }

            // Skip if we've already processed this student for this year
            $key = $studentId . '_' . $yearCode;
            if (in_array($key, $processedStudents)) {
                continue;
            }

            // Check if student exists
            $student = DB::table('students')->where('student_number', $studentId)->first();
            if (!$student) {
                Log::warning("Student not found: {$studentId}");
                continue;
            }

            // Parse year code (e.g., 20181 -> Year 2018, Semester 1)
            $year = substr($yearCode, 0, 4);
            $semesterNumber = substr($yearCode, 4, 1);

            // Map semester number to semester name (1=Fall, 2=Spring, 3=Summer)
            $semesterName = match((int)$semesterNumber) {
                1 => 'Fall',
                2 => 'Spring',
                3 => 'Summer',
                default => 'Fall'
            };

            // Find the semester
            $semesterRecord = DB::table('semesters')
                ->where('Year', $year)
                ->where('SemesterName', $semesterName)
                ->first();

            if (!$semesterRecord) {
                Log::warning("Semester not found: Year {$year}, Semester {$semesterNumber}");
                continue;
            }

            // Check if student_semester relationship already exists
            $exists = DB::table('student_semesters')
                ->where('student_id', $student->id)
                ->where('semester_id', $semesterRecord->id)
                ->exists();

            if (!$exists) {
                $studentSemesters[] = [
                    'student_id' => $student->id,
                    'semester_id' => $semesterRecord->id,
                    'status' => 'enrolled',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $processedStudents[] = $key;
                Log::info("Added student {$studentId} to semester {$year}-{$semesterNumber}");
            }
        }

        fclose($file);

        // Insert all student_semester relationships in batches
        if (!empty($studentSemesters)) {
            $batchSize = 1000; // Process 1000 records at a time
            $batches = array_chunk($studentSemesters, $batchSize);
            
            foreach ($batches as $batch) {
                DB::table('student_semesters')->insert($batch);
                Log::info("Inserted batch of " . count($batch) . " student-semester relationships");
            }
            
            Log::info("Total inserted " . count($studentSemesters) . " student-semester relationships");
        }

        Log::info('--- StudentSemesterSeeder has finished ---');
    }
}
