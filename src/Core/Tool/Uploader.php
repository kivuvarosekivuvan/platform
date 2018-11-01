<?php

/**
 * Ushahidi Platform Uploader Tool
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Platform
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\Core\Tool;

use League\Flysystem\Filesystem;
use Ushahidi\Core\Tool\UploadData;
use Ushahidi\Core\Tool\FileData;
use League\Flysystem\Util\MimeType;
use Ushahidi\App\Multisite\UsesSiteInfo;

class Uploader
{
    use UsesSiteInfo;
    private $fs;

    public function __construct(Filesystem $fs)
    {
        $this->fs = $fs;
    }

    /**
     * Given an upload, move it onto the application filesystem and return
     * the file information.
     *
     * When manually setting the filename, be sure to include the unique prefix!
     *
     * @param  Ushahidi\Core\Tool\UploadData $upload
     * @param  String  $filename  file to overwrite or create
     * @return Ushahidi\Core\Tool\FileData
     */
    public function upload(UploadData $file, $filename = null)
    {
        if (!$filename) {
            // Use the upload filename, adding a unique prefix to prevent collisions.
            $filename = uniqid() . '-' . $file->name;
        }

        // Avoid possible issues with case sensitivity by forcing all files
        // to be made lowercase. Also replace possibly invalid characters in filename
        $filename = strtolower(preg_replace('/[^\pL\pN\-\_\s\.]+/u', '', $filename));


        // Add the first and second letters of filename to the directory path
        // to help segment the files, producing a more reasonable amount of
        // files per directory, eg: abc-myfile.png -> a/b/abc-myfile.png
        $filepath = implode('/', [
            $this->getSite()->getCdnPrefix(),
            $filename[0],
            $filename[1],
            $filename,
            ]);

        // Remove any leading slashes on the filename, path is always relative.
        $filepath = ltrim($filepath, '/');

        // Stream the temporary file into the filesystem, creating or overwriting.
        $stream = fopen($file->tmp_name, 'r+');
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $mimeType = MimeType::detectByFileExtension($extension) ?: 'text/plain';
        $config = ['mimetype' => $mimeType];

        try {
            $this->fs->putStream($filepath, $stream, $config);
        } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
            // Flysystem and FlysystemRackspace are very leaky abstractions
            // so we have to manually catch guzzle errors here
            $response = $e->getResponse();

            throw new \InvalidArgumentException('Could not upload file: '. $response->getBody(true));
        } catch (\Guzzle\Http\Exception\BadResponseException $e) {
            // Flysystem and FlysystemRackspace are very leaky abstractions
            // so we have to manually catch guzzle errors here

            throw new \InvalidArgumentException('Could not upload file');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // Get meta information about the file.
        $size = $this->fs->getSize($filepath);
        $type = $this->fs->getMimetype($filepath);

        // Get width and height of file, if it is an image.
        if ($this->isImage($type)) {
            list($width, $height) = @getimagesize($file->tmp_name);
        } else {
            $width = $height = null;
        }

        // And return the new file information.
        return new FileData([
            'file'   => $filepath,
            'size'   => $size,
            'type'   => $type,
            'width'  => $width,
            'height' => $height,
            ]);
    }

    /**
     * Clean up file if upload failed
     * @param  $file  filepath
     */
    public function delete($file)
    {
        if ($this->fs->has($file)) {
            $this->fs->delete($file);
        }
    }

    private function isImage($type)
    {
        return (bool) preg_match('#^image/#', $type);
    }
}
