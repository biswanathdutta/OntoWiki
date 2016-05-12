<?php
/**
 * Parses and verifies the TYPO3 copyright notice.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   TYPO3SniffPool
 * @author    Stefano Kowalke <blueduck@mailbox.org>
 * @copyright 2015 Stefano Kowalke
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @link      https://github.com/typo3-ci/TYPO3SniffPool
 */

/**
 * Parses and verifies the TYPO3 copyright notice.
 *
 * @category  PHP
 * @package   TYPO3SniffPool
 * @author    Stefano Kowalke <blueduck@mailbox.org>
 * @copyright 2015 Stefano Kowalke
 * @license   http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @link      https://github.com/typo3-ci/TYPO3SniffPool
 */

class Ontowiki_Sniffs_Commenting_FileCommentSniff implements PHP_CodeSniffer_Sniff
{
    /**
     * The file comment in TYPO3 CMS must be the copyright notice.
     *
     * @var array
     */
    protected $copyright = array(
                            1  => "/**\n",
                            2  => " * This file is part of the {@link http://ontowiki.net OntoWiki} project.\n",
                            3  => " *\n",
                            4  => "",
                            5  => " * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)\n",
                            6  => " */",
                           );

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_OPEN_TAG);

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return int
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        // Find the next non whitespace token.
        $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);
        $noGit= true;
        //test if a git exists to get the years from 'git log'
        exec('([ -d .git ] && echo .git) || git rev-parse --git-dir 2> /dev/null', $gitTest);
        if(!empty($gitTest)){
            //test if a git entry exists to get the years from 'git log'
            exec('git log --reverse ' . $phpcsFile->getFilename() . ' | head -3' , $outputCreationYear);
            if(!empty($outputCreationYear)) {
                preg_match("/( )[0-9]{4}( )/", $outputCreationYear[2],$gitOldYearArray);
                $gitYearOld=str_replace(' ','',$gitOldYearArray[0]);
                exec('git log -1 ' . $phpcsFile->getFilename(), $outputLastEditYear);
                preg_match("/( )[0-9]{4}( )/", $outputLastEditYear[2],$gitNewYearArray);
                $gitYearNew=str_replace(' ','',$gitNewYearArray[0]);
                if(strcmp($gitYearOld,$gitYearNew)!=0)
                {
                    $gitYearOld .='-';
                    $gitYearOld .=$gitYearNew;
                }
                $year = " * @copyright Copyright (c) " . $gitYearOld . ", {@link http://aksw.org AKSW}\n";
                $this->copyright[4]= $year;
                $noGit = false;
            }
        }
        if($noGit) {
            if(count($tokens)>15)
            preg_match("/( )[0-9]{4}(-[0-9]{4})?/",$tokens[$commentStart+15]['content'],$nonGitYear);
            //tests if the file has no year/wrong editing and the year can't be found
            if(!empty($nonGitYear))
            {
                $year = " * @copyright Copyright (c) " . str_replace(' ','',$nonGitYear[0]) . ", {@link http://aksw.org AKSW}\n";
                $this->copyright[4]= $year;
            }
        }
        $tokenizer = new PHP_CodeSniffer_Tokenizers_Comment();
        $expectedString = implode($this->copyright);
        $expectedTokens = $tokenizer->tokenizeString($expectedString, PHP_EOL, 0);
        // Allow namespace statements at the top of the file.
        if ($tokens[$commentStart]['code'] === T_NAMESPACE) {
            $semicolon    = $phpcsFile->findNext(T_SEMICOLON, ($commentStart + 1));
            $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($semicolon + 1), null, true);
        }

        if ($tokens[$commentStart]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $fix = $phpcsFile->addFixableError(
                'Copyright notice must start with /**; but /* was found!',
                $commentStart,
                'WrongStyle'
            );

            if ($fix === true) {
                $phpcsFile->fixer->replaceToken($commentStart, "/**");
            }
            return;
        }

        $commentEnd = ($phpcsFile->findNext(T_WHITESPACE, ($commentStart + 1)) - 1);
        if ($tokens[$commentStart]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $phpcsFile->addError('Copyright notice missing', $commentStart, 'NoCopyrightFound');

            return;
        }
        $commentEndLine = $tokens[$commentEnd]['line'];
        $commentStartLine = $tokens[$commentStart]['line'];
        if ((($commentEndLine - $commentStartLine) + 1) < count($this->copyright)) {
            $phpcsFile->addError(
                'Copyright notice too short',
                $commentStart,
                'CommentTooShort'
            );
            return;
        } else if ((($commentEndLine - $commentStartLine) + 1) > count($this->copyright)) {
            $phpcsFile->addError(
                'Copyright notice too long',
                $commentStart,
                'CommentTooLong'
            );
            return;
        }
        $j = 0;
        for ($i = $commentStart; $i <= $commentEnd; $i++) {
            if ($tokens[$i]['content'] !== $expectedTokens[$j]["content"]) {
                $error = 'Found wrong part of copyright notice. Expected "%s", but found "%s"';
                $data  = array(
                          trim($expectedTokens[$j]["content"]),
                          trim($tokens[$i]['content']),
                         );
                $fix   = $phpcsFile->addFixableError($error, $i, 'WrongText', $data);

                if ($fix === true) {
                    $phpcsFile->fixer->replaceToken($i, $expectedTokens[$j]["content"]);
                }
            }

            $j++;
        }

        return;

    }//end process()


}//end class
