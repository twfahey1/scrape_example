// emailScraper.js
const puppeteer = require('puppeteer');

async function scrapeEmail(url) {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();
    await page.goto(url);

    // You might need to adjust the selector to match the structure of the page
    const email = await page.evaluate(() => {
        let emailElement = document.querySelector('a[href^="mailto:"]');
        return emailElement ? emailElement.href : 'N/A';
    });

    await browser.close();
    return email;
}

// The URL could be passed as a command-line argument
const url = process.argv[2];

scrapeEmail(url).then(email => {
    console.log(email); // Output the email to the console
});
