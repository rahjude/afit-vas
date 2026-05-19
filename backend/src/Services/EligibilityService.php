<?php

namespace App\Services;

class EligibilityService
{
    private const CREDIT_GRADES = ['A1', 'B2', 'B3', 'C4', 'C5', 'C6'];

    private const SUBJECT_ALIASES = [
        'mathematics'          => ['maths', 'math', 'general mathematics'],
        'english language'     => ['english', 'eng lang', 'use of english'],
        'physics'              => ['phy'],
        'chemistry'            => ['chem'],
        'biology'              => ['bio', 'general biology'],
        'further mathematics'  => ['further maths', 'f.maths', 'add maths', 'additional mathematics'],
        'technical drawing'    => ['tech drawing', 'engineering drawing'],
        'economics'            => ['econs'],
        'government'           => ['govt'],
        'literature in english' => ['lit in eng', 'literature', 'lit-in-english'],
        'agricultural science' => ['agric', 'agriculture'],
        'computer studies'     => ['computer science', 'computer', 'data processing'],
        'civic education'      => ['civic edu', 'civic'],
    ];

    public function check(array $oLevelResults, array $requiredSubjects, int $minCredits = 5): array
    {
        $subjectDetails = [];
        $creditsObtained = 0;
        $missingSubjects = [];

        foreach ($requiredSubjects as $required) {
            $requiredLower = strtolower(trim($required));
            $found = false;
            $matchedResult = null;

            foreach ($oLevelResults as $result) {
                $subjectLower = strtolower(trim($result['subject'] ?? ''));
                if ($this->matchesSubject($subjectLower, $requiredLower)) {
                    $found = true;
                    $matchedResult = $result;
                    break;
                }
            }

            if (!$found) {
                $missingSubjects[] = $required;
                $subjectDetails[] = [
                    'subject'    => $required,
                    'status'     => 'missing',
                    'grade'      => null,
                    'has_credit' => false,
                ];
                continue;
            }

            $grade = strtoupper(trim($matchedResult['grade'] ?? ''));
            $hasCredit = in_array($grade, self::CREDIT_GRADES, true);

            if ($hasCredit) {
                $creditsObtained++;
            } else {
                $missingSubjects[] = $required;
            }

            $subjectDetails[] = [
                'subject'    => $required,
                'status'     => $found ? 'found' : 'missing',
                'grade'      => $grade,
                'has_credit' => $hasCredit,
            ];
        }

        $passed = $creditsObtained >= $minCredits && empty(array_filter($missingSubjects, function ($s) use ($subjectDetails) {
            foreach ($subjectDetails as $d) {
                if (strtolower($d['subject']) === strtolower($s) && $d['status'] === 'missing') {
                    return true;
                }
            }
            return false;
        }));

        $passed = $creditsObtained >= $minCredits;

        $notes = $passed
            ? "Applicant meets the minimum requirement of {$minCredits} credits in required subjects."
            : "Applicant has {$creditsObtained} of {$minCredits} required credits.";

        if (!empty($missingSubjects)) {
            $notes .= ' Missing/insufficient: ' . implode(', ', $missingSubjects) . '.';
        }

        return [
            'passed'           => $passed,
            'credits_obtained' => $creditsObtained,
            'credits_required' => $minCredits,
            'missing_subjects' => $missingSubjects,
            'details'          => $subjectDetails,
            'notes'            => $notes,
        ];
    }

    private function matchesSubject(string $input, string $required): bool
    {
        if ($input === $required) {
            return true;
        }

        // Check aliases
        foreach (self::SUBJECT_ALIASES as $canonical => $aliases) {
            if ($canonical === $required || in_array($required, $aliases, true)) {
                if ($input === $canonical || in_array($input, $aliases, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
