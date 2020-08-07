<?php declare(strict_types=1);

namespace Google\Generator;

class Formatter
{
    public static function Format(string $code): string
    {
        $fixers = [
            new \PhpCsFixer\Fixer\Whitespace\IndentationTypeFixer(), // 50
            new \PhpCsFixer\Fixer\Semicolon\NoEmptyStatementFixer(), // 26
            new \PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer(), // 2
            new \PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer(), // 1
            new \PhpCsFixer\Fixer\PhpTag\LinebreakAfterOpeningTagFixer(), // 0
            new \PhpCsFixer\Fixer\ClassNotation\ClassDefinitionFixer(), // 0
            new \PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer(), // -21
            new \PhpCsFixer\Fixer\Basic\BracesFixer(), // -25
            new \PhpCsFixer\Fixer\Whitespace\ArrayIndentationFixer(), // -31
            new \PhpCsFixer\Fixer\Whitespace\SingleBlankLineAtEofFixer(), // -50
        ];

        $tokens = \PhpCsFixer\Tokenizer\Tokens::fromCode($code);

        $fakeFile = new \SplFileInfo('');
        foreach ($fixers as $fixer) {
            $fixer->fix($fakeFile, $tokens);
        }

        $code = $tokens->generateCode();
        return $code;
    }
}
