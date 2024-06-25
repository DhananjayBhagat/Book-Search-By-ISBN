<?php

function getBookDetailsFromGoogleAPI($title, $author) {
    // Construct the URL to search for title and author
    $url = 'https://www.googleapis.com/books/v1/volumes?q=intitle:' . urlencode($title) . '+inauthor:' . urlencode($author);
    $response = @file_get_contents($url);

    if ($response === false) {
        echo "Error fetching data from Google Books API for title '$title' and author '$author'.\n";
        return false;
    }

    $data = json_decode($response, true);

    // Check if there are any items (books) found
    if (isset($data['items']) && count($data['items']) > 0) {
        // Get the first book (most relevant result)
        $book = $data['items'][0];
        $volumeInfo = $book['volumeInfo'];

        // Fetch ISBNs if available
        $isbn10 = '';
        $isbn13 = '';
        if (isset($volumeInfo['industryIdentifiers'])) {
            foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                if ($identifier['type'] === 'ISBN_10') {
                    $isbn10 = $identifier['identifier'];
                } elseif ($identifier['type'] === 'ISBN_13') {
                    $isbn13 = $identifier['identifier'];
                }
            }
        }

        return [
            'Title' => $volumeInfo['title'],
            // 'Authors' => implode(', ', $volumeInfo['authors']),
            'Authors' => $author,
            'ISBN10' => $isbn10,
            'ISBN13' => $isbn13,
            'PublisherName' => $volumeInfo['publisher'] ?? '',
        ];
    }

    echo "No matching book found for title '$title' and author '$author' in Google Books API.\n";
    return false;
}

function exportToCSV($filename, $data) {
    if (($fp = fopen($filename, 'w')) === false) {
        echo "Error creating file '$filename'.\n";
        return;
    }

    fputcsv($fp, ['Title', 'Authors', 'ISBN10', 'ISBN13', 'Biblionumber', 'PublisherName']);

    foreach ($data as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);

    echo "CSV file '$filename' has been successfully created with book details.\n";
}

function processCSV($inputFilename, $outputFilename) {
    if (($handle = fopen($inputFilename, 'r')) === false) {
        echo "Error opening file '$inputFilename'.\n";
        return;
    }

    $allBookDetails = [];
    $header = fgetcsv($handle); // Get header row (assuming it's skipped)
    $numColumns = count($header); // Number of columns in CSV

    while (($data = fgetcsv($handle, 1000, '~')) !== false) {
        // Extract fields from CSV row with increased positions
        $titleFromCSV = isset($data[1]) ? trim($data[1]) : '';
        $author = isset($data[2]) ? trim($data[2]) : '';
        $biblionumber = isset($data[3]) ? trim($data[3]) : ''; // Biblionumber from CSV
        $publishername = isset($data[4]) ? trim($data[4]) : ''; // Publisher from CSV

        if (!empty($titleFromCSV) && !empty($author)) {
            echo "Fetching book: $titleFromCSV by $author\n";

            // Fetch book details from Google Books API
            $bookDetails = getBookDetailsFromGoogleAPI($titleFromCSV, $author);

            if ($bookDetails !== false) {
                $allBookDetails[] = [
                    $bookDetails['Title'],
                    $bookDetails['Authors'],
                    $bookDetails['ISBN10'],
                    $bookDetails['ISBN13'],
                    $biblionumber, // Use Biblionumber from CSV
                    $publishername, // Use Publisher from CSV
                ];
            } else {
                $allBookDetails[] = [
                    $titleFromCSV,
                    $author,
                    '', // Placeholder for ISBN10 (not fetched)
                    '', // Placeholder for ISBN13 (not fetched)
                    $biblionumber,
                    $publishername,
                ];
            }
        } else {
            $allBookDetails[] = [
                $titleFromCSV,
                $author,
                '',
                '',
                $biblionumber,
                $publishername,
            ];
        }
    }

    fclose($handle);

    // Export collected book details to CSV
    exportToCSV($outputFilename, $allBookDetails);
}

$inputFilename = 'abc.csv';
$outputFilename = 'a.csv';

processCSV($inputFilename, $outputFilename);
?>
