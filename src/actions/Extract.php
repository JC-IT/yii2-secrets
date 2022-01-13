<?php

declare(strict_types=1);

namespace JCIT\secrets\actions;

use JCIT\secrets\adapters\Yii;
use JCIT\secrets\interfaces\StorageInterface;
use JCIT\secrets\SecretOccurrence;
use yii\base\Action;
use yii\base\InvalidArgumentException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;

class Extract extends Action
{
    /**
     * @var string[]
     */
    public array $calls = ['$secrets->get', '$secrets->getAndThrowOnNull'];

    /**
     * @var string[]
     */
    public array $except = [
        '.*',
        '/.*',
        '/messages',
        '/tests',
        '/runtime',
        '/vendor',
    ];

    /**
     * @var string[]
     */
    public array $only = ['*.php'];

    public string $sourcePath = '@app/';

    /**
     * @param string[] $calls
     * @return SecretOccurrence[]
     */
    protected function extractSecrets(string $fileName, array $calls): array
    {
        $this->stdout('Extracting secrets from ');
        $this->stdout($fileName, Console::FG_CYAN);
        $this->stdout("...\n");

        /** @var string $subject */
        $subject = file_get_contents($fileName);
        $secrets = [];
        $tokens = token_get_all($subject);
        foreach ($calls as $call) {
            $callTokens = token_get_all('<?php ' . $call);
            array_shift($callTokens);
            $secrets = array_merge_recursive($secrets, $this->extractSecretsFromTokens($fileName, $tokens, $callTokens));
        }

        $this->stdout("\n");

        return $secrets;
    }

    /**
     * @param array<int, array<int, int|string>|string> $tokens
     * @param array<int, array<int, int|string>|string> $callTokens
     * @return array<string, array<int, SecretOccurrence>>
     */
    protected function extractSecretsFromTokens(string $file, array $tokens, array $callTokens): array
    {
        $secrets = [];
        $calllatorTokensCount = count($callTokens);
        $matchedTokensCount = 0;
        $buffer = [];
        $pendingParenthesisCount = 0;

        foreach ($tokens as $tokenIndex => $token) {
            // finding out secret call
            if ($matchedTokensCount < $calllatorTokensCount) {
                if ($this->tokensEqual($token, $callTokens[$matchedTokensCount])) {
                    $matchedTokensCount++;
                } else {
                    $matchedTokensCount = 0;
                }
            } elseif ($matchedTokensCount === $calllatorTokensCount) {
                // secret found

                // end of function call
                if ($this->tokensEqual(')', $token)) {
                    $pendingParenthesisCount--;

                    if ($pendingParenthesisCount === 0) {
                        // end of secret call or end of something that we can't extract
                        if (isset($buffer[0][0]) && $buffer[0][0] === T_CONSTANT_ENCAPSED_STRING) {
                            $default = null;
                            if (isset($buffer[1], $buffer[2][0]) && $buffer[2][0] === T_CONSTANT_ENCAPSED_STRING) {
                                /** @var string $tokenContent */
                                $tokenContent = $buffer[2][1];
                                $default = stripcslashes(mb_substr($tokenContent, 1, -1));
                            }

                            /** @var string $tokenContent */
                            $tokenContent = $buffer[0][1];
                            $secrets[stripcslashes(mb_substr($tokenContent, 1, -1))][] = new SecretOccurrence($file, $this->getLine($buffer), $default);
                        } else {
                            // invalid call or dynamic call we can't extract
                            $line = Console::ansiFormat((string) $this->getLine($buffer), [Console::FG_CYAN]);
                            $skipping = Console::ansiFormat('Skipping line', [Console::FG_YELLOW]);
                            $this->stdout("$skipping $line. Make sure all are static strings.\n");
                        }

                        // prepare for the next match
                        $matchedTokensCount = 0;
                        $pendingParenthesisCount = 0;
                        $buffer = [];
                    } else {
                        $buffer[] = $token;
                    }
                } elseif ($this->tokensEqual('(', $token)) {
                    // count beginning of function call, skipping secret call beginning
                    if ($pendingParenthesisCount > 0) {
                        $buffer[] = $token;
                    }
                    $pendingParenthesisCount++;
                } elseif (isset($token[0]) && !in_array($token[0], [T_WHITESPACE, T_COMMENT], true)) {
                    // ignore comments and whitespaces
                    $buffer[] = $token;
                }
            }
        }

        return $secrets;
    }

    /**
     * @param array<int, array<int, int|string>|string> $tokens
     */
    protected function getLine(array $tokens): int
    {
        $result = -1;

        foreach ($tokens as $token) {
            if (isset($token[2])) {
                $result = (int) $token[2];
                break;
            }
        }

        return $result;
    }

    public function run(
        Yii $yii,
        StorageInterface $secretStorage,
    ): int {
        $sourcePath = $yii->getAlias($this->sourcePath);

        if ($sourcePath === false) {
            throw new InvalidArgumentException('Unknown alias used in sourcePath.');
        }

        $files = FileHelper::findFiles(
            $sourcePath,
            [
                'except' => $this->except,
                'only' => $this->only,
            ]
        );

        $secrets = [];
        foreach ($files as $file) {
            $secrets = array_merge_recursive($secrets, $this->extractSecrets($file, $this->calls));
        }

        foreach ($secrets as $secret => $occurrences) {
            $secretStorage->prepare($secret, $occurrences);
        }

        return ExitCode::OK;
    }

    protected function stdout(string $string): void
    {
        if ($this->controller instanceof Controller) {
            $args = func_get_args();
            array_shift($args);
            $this->controller->stdout($string, ...$args);
        }
    }

    /**
     * @param array<int, int|string>|string $a
     * @param array<int, int|string>|string $b
     * @return bool
     */
    protected function tokensEqual(array|string $a, array|string $b): bool
    {
        if (is_string($a) && is_string($b)) {
            return $a === $b;
        }
        if (isset($a[0], $a[1], $b[0], $b[1])) {
            return $a[0] === $b[0] && $a[1] == $b[1];
        }

        return false;
    }
}
