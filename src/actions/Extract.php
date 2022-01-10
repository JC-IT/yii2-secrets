<?php
declare(strict_types=1);

namespace JCIT\secrets\actions;

use JCIT\secrets\interfaces\StorageInterface;
use JCIT\secrets\SecretOccurrence;
use yii\base\Action;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\FileHelper;

class Extract extends Action
{
    public array $calls = ['$secrets->get', '$secrets->getAndThrowOnEmpty'];
    public array $except = [
        '.*',
        '/.*',
        '/messages',
        '/tests',
        '/runtime',
        '/vendor',
    ];
    public array $only = ['*.php'];
    public string $sourcePath = '@app/';

    protected function extractSecrets($fileName, $calls)
    {
        $this->stdout('Extracting secrets from ');
        $this->stdout($fileName, Console::FG_CYAN);
        $this->stdout("...\n");

        $subject = file_get_contents($fileName);
        $secrets = [];
        $tokens = token_get_all($subject);
        foreach ((array) $calls as $call) {
            $callTokens = token_get_all('<?php ' . $call);
            array_shift($callTokens);
            $secrets = array_merge_recursive($secrets, $this->extractSecretsFromTokens($fileName, $tokens, $callTokens));
        }

        $this->stdout("\n");

        return $secrets;
    }

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
                                $default = stripcslashes(mb_substr($buffer[2][1], 1, -1));
                            }

                            $secrets[stripcslashes(mb_substr($buffer[0][1], 1, -1))][] = new SecretOccurrence($file, $this->getLine([0]), $default);
                        } else {
                            // invalid call or dynamic call we can't extract
                            $line = Console::ansiFormat($this->getLine($buffer), [Console::FG_CYAN]);
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

                    // If we are not yet inside the secret call, make sure that it's beginning of the real secret call.
                    if ($pendingParenthesisCount === 0) {
                        $previousTokenIndex = $tokenIndex - $matchedTokensCount - 1;
                        if (is_array($tokens[$previousTokenIndex])) {
                            $previousToken = $tokens[$previousTokenIndex][0];
                            if (in_array($previousToken, [T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM], true)) {
                                $matchedTokensCount = 0;
                                continue;
                            }
                        }
                    }

                    if ($pendingParenthesisCount > 0) {
                        $buffer[] = $token;
                    }
                    $pendingParenthesisCount++;
                } elseif (isset($token[0]) && !in_array($token[0], [T_WHITESPACE, T_COMMENT])) {
                    // ignore comments and whitespaces
                    $buffer[] = $token;
                }
            }
        }

        return $secrets;
    }

    protected function getLine(array $tokens): int
    {
        foreach ($tokens as $token) {
            if (isset($token[2])) {
                return $token[2];
            }
        }

        return 0;
    }

    public function run(
        StorageInterface $secretStorage
    ): int {
        $sourcePath = \Yii::getAlias($this->sourcePath);
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

    protected function stdOut(string $string): int|bool
    {
        $args = func_get_args();
        array_shift($args);
        return $this->controller->stdout($string, ...$args);
    }

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
