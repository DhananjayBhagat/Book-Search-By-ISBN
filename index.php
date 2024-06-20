<?php

function getBookDetailsFromGoogleAPI($title)
{
    $url = 'https://www.googleapis.com/books/v1/volumes?q=intitle:' . urlencode($title);

    $response = file_get_contents($url);

    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);

    if (isset($data['items'])) {
        foreach ($data['items'] as $item) {
            $volumeInfo = $item['volumeInfo'];

            $isbn10 = '';
            $isbn13 = '';

            if (isset($volumeInfo['industryIdentifiers'])) {
                foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                    if ($identifier['type'] == 'ISBN_10') {
                        $isbn10 = $identifier['identifier'];
                    } elseif ($identifier['type'] == 'ISBN_13') {
                        $isbn13 = $identifier['identifier'];
                    }
                }
            }

            if (!empty($isbn10) || !empty($isbn13)) {
                return [
                    'Title' => $title,
                    'ISBN10' => !empty($isbn10) ? $isbn10 : 'ISBN10 Unavailable',
                    'ISBN13' => !empty($isbn13) ? $isbn13 : 'ISBN13 Unavailable',
                ];
            }
        }
    }

    return false;
}

function getBookDetailsFromOpenLibraryAPI($title)
{
    $url = 'https://openlibrary.org/search.json?q=' . urlencode($title);

    $response = file_get_contents($url);

    if ($response === false) {
        return false;
    }

    $data = json_decode($response, true);

    if (isset($data['docs'][0])) {
        $bookInfo = $data['docs'][0];

        $isbn10 = '';
        $isbn13 = '';

        if (isset($bookInfo['isbn'])) {
            foreach ($bookInfo['isbn'] as $isbn) {
                if (strlen($isbn) == 10) {
                    $isbn10 = $isbn;
                } elseif (strlen($isbn) == 13) {
                    $isbn13 = $isbn;
                }
            }
        }

        return [
            'Title' => $title,
            'ISBN10' => !empty($isbn10) ? $isbn10 : 'ISBN10 Unavailable',
            'ISBN13' => !empty($isbn13) ? $isbn13 : 'ISBN13 Unavailable',
        ];
    }

    return false;
}

function exportToCSV($filename, $data)
{
    $fp = fopen($filename, 'w');

    // Modify the headers to include Biblionumber
    fputcsv($fp, ['Title', 'ISBN10', 'ISBN13', 'Biblionumber']);

    foreach ($data as $row) {
        // Append Biblionumber to each row
        fputcsv($fp, $row);
    }

    fclose($fp);

    echo "CSV file '$filename' has been successfully created with book details.\n";
}

$inputFilename = 'uniquetitle.csv';
$outputFilename = 'exportedreport.csv';

if (($handle = fopen($inputFilename, 'r')) !== false) {
    $allBookDetails = [];

    while (($data = fgetcsv($handle, 1000, '~')) !== false) {
        $title = isset($data[1]) ? trim($data[1]) : '';
        $biblionumber = isset($data[3]) ? trim($data[3]) : ''; // Read Biblionumber from CSV

        if (!empty($title)) {
            $bookDetails = getBookDetailsFromGoogleAPI($title);

            if ($bookDetails !== false) {
                // Include Biblionumber in the book details array
                $bookDetails['Biblionumber'] = $biblionumber;
                $allBookDetails[] = [
                    $bookDetails['Title'],
                    $bookDetails['ISBN10'],
                    $bookDetails['ISBN13'],
                    $bookDetails['Biblionumber'], // Add Biblionumber to the row
                ];
                continue; // Skip Open Library API if Google Books API found ISBN
            }

            $bookDetails = getBookDetailsFromOpenLibraryAPI($title);

            if ($bookDetails !== false) {
                // Include Biblionumber in the book details array
                $bookDetails['Biblionumber'] = $biblionumber;
                $allBookDetails[] = [
                    $bookDetails['Title'],
                    $bookDetails['ISBN10'],
                    $bookDetails['ISBN13'],
                    $bookDetails['Biblionumber'], // Add Biblionumber to the row
                ];
            } else {
                $allBookDetails[] = [
                    $title,
                    'ISBN10 Unavailable',
                    'ISBN13 Unavailable',
                    $biblionumber, // Add Biblionumber even if book details are unavailable
                ];
            }
        }
    }

    fclose($handle);

    exportToCSV($outputFilename, $allBookDetails);
} else {
    echo "Error opening file $inputFilename\n";
}
?>
