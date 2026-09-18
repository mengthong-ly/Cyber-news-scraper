import { Form, Head } from '@inertiajs/react';
import ItemController from '@/actions/App/Http/Controllers/ItemController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/items';

const textareaClass =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] dark:bg-input/30';
const selectClass =
    'border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs dark:bg-input/30';

export default function CreateItem({ categories }: { categories: string[] }) {
    return (
        <>
            <Head title="Add a post" />
            <div className="mx-auto w-full max-w-2xl p-4">
                <Heading
                    title="Add a post"
                    description="Record a public post you found on a platform the tool cannot read (Facebook, TikTok, X, Telegram…). Only record public content."
                />

                <Form
                    {...ItemController.store.form()}
                    encType="multipart/form-data"
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="url">Link to the post</Label>
                                <Input
                                    id="url"
                                    name="url"
                                    type="url"
                                    required
                                    placeholder="https://"
                                />
                                <InputError message={errors.url} />
                            </div>

                            <div className="grid gap-2 sm:grid-cols-[2fr_1fr] sm:gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="title">
                                        Headline or first line
                                    </Label>
                                    <Input
                                        id="title"
                                        name="title"
                                        required
                                        maxLength={500}
                                    />
                                    <InputError message={errors.title} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="platform">Platform</Label>
                                    <select
                                        id="platform"
                                        name="platform"
                                        className={selectClass}
                                        defaultValue="Facebook"
                                    >
                                        {[
                                            'Facebook',
                                            'TikTok',
                                            'X',
                                            'Telegram',
                                            'Instagram',
                                            'YouTube',
                                            'Website',
                                            'Other',
                                        ].map((p) => (
                                            <option key={p}>{p}</option>
                                        ))}
                                    </select>
                                    <InputError message={errors.platform} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="excerpt">
                                    Post text (copy the relevant part)
                                </Label>
                                <textarea
                                    id="excerpt"
                                    name="excerpt"
                                    className={textareaClass}
                                    maxLength={5000}
                                />
                                <InputError message={errors.excerpt} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="category">Category</Label>
                                    <select
                                        id="category"
                                        name="category"
                                        className={selectClass}
                                        defaultValue=""
                                    >
                                        <option value="">
                                            Detect automatically
                                        </option>
                                        {categories.map((c) => (
                                            <option
                                                key={c}
                                                value={c}
                                                className="capitalize"
                                            >
                                                {c}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="severity">Severity</Label>
                                    <select
                                        id="severity"
                                        name="severity"
                                        className={selectClass}
                                        defaultValue=""
                                    >
                                        <option value="">
                                            Detect automatically
                                        </option>
                                        <option value="5">
                                            5 — active attack or leak on a
                                            Cambodian institution
                                        </option>
                                        <option value="4">
                                            4 — credible threat or impersonation
                                        </option>
                                        <option value="3">
                                            3 — significant event
                                        </option>
                                        <option value="2">2 — notable</option>
                                        <option value="1">
                                            1 — background
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Analyst notes</Label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    className={textareaClass}
                                    maxLength={5000}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="screenshot">
                                    Screenshot (optional, up to 5 MB)
                                </Label>
                                <Input
                                    id="screenshot"
                                    name="screenshot"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                />
                                <InputError message={errors.screenshot} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="is_cambodia">
                                        Concerns Cambodia?
                                    </Label>
                                    <select
                                        id="is_cambodia"
                                        name="is_cambodia"
                                        className={selectClass}
                                        defaultValue="0"
                                    >
                                        <option value="1">Yes</option>
                                        <option value="0">
                                            No / detect automatically
                                        </option>
                                    </select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ai_allowed">
                                        AI translation and labelling
                                    </Label>
                                    <select
                                        id="ai_allowed"
                                        name="ai_allowed"
                                        className={selectClass}
                                        defaultValue="1"
                                    >
                                        <option value="1">Allowed</option>
                                        <option value="0">
                                            Not allowed (e.g. Telegram content
                                            before legal review)
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <Button disabled={processing}>Save post</Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CreateItem.layout = {
    breadcrumbs: [
        { title: 'Feed', href: index() },
        { title: 'Add a post', href: create() },
    ],
};
