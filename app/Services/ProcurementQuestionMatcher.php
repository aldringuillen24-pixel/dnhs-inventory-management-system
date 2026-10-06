<?php

namespace App\Services;

/**
 * Recognises questions about what to buy, reorder or procure.
 *
 * The assistant does not answer procurement questions. They belong to AI
 * Decision Support on the Demand Forecast page, so this predicate has exactly
 * one job: recognise that a question is procurement-shaped and hand it off
 * rather than answering it against inventory facts.
 *
 * It was previously implemented twice — once as the chat gate and once inside
 * the answer-generation service — with two different pattern sets that
 * disagreed in both directions. This is the single implementation both call.
 */
class ProcurementQuestionMatcher
{
    /**
     * Deliberately bounded. It must catch restock and procurement-priority
     * wording without swallowing ordinary stock or forecast questions, which
     * this assistant still answers.
     */
    private const PATTERNS = [
        // Chat-gate wording: a why/need/should/must question about acquiring.
        '/\b(?:why|need|should|must)\b.*\b(?:procure|procurement|purchase|buy|order)\b/iu',
        // Answer-redirect wording.
        '/\bwhat (?:should|do) (?:we|i) (?:buy|purchase|order|restock|procure)\b/i',
        '/\b(?:purchase|procure|restock|re-?order|replenish)\s+(?:priorit|list|first|plan)/i',
        '/\bprocurement\s+(?:priorit|list|plan|recommend)/i',
        '/\bshould (?:we|i) (?:buy|order|restock)\b/i',
        '/\bwhich items? (?:should|do) (?:we|i) (?:buy|order|restock|procure)\b/i',
        '/\b(?:top|highest)\s+procurement\b/i',
        '/\bwhat can (?:wait|defer)\b/i',
        '/\bdefer(?:able)?\s+purchase/i',
    ];

    public function matches(string $question): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $question) === 1) {
                return true;
            }
        }

        return false;
    }
}