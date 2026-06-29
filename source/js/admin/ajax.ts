declare const tsSettings: { ajaxUrl: string } & Record<string, string>;

export async function ajaxPost(params: Record<string, string>): Promise<unknown> {
    const response = await fetch(tsSettings.ajaxUrl, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams(params).toString(),
    });
    return response.json();
}
