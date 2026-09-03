<?php

declare(strict_types=1);

namespace Tests\Unit\Email;

use App\Modules\Email\Domain\SubjectTagger;
use PHPUnit\Framework\TestCase;

/**
 * The reference in the subject, exactly once.
 *
 * The headers are how threading is supposed to work. This is what happens when
 * they do not: a client that strips unknown headers, a webmail that rewrites
 * the message, a customer replying from a brand-new email. In each of those the
 * subject tag is the only thing left connecting a reply to its ticket.
 */
final class SubjectTaggerTest extends TestCase
{
    public function test_it_tags_an_untagged_subject(): void
    {
        $this->assertSame(
            '[#TKT-000042] Invoice is wrong',
            SubjectTagger::tag('Invoice is wrong', 'TKT-000042'),
        );
    }

    public function test_it_does_not_tag_twice(): void
    {
        $once = SubjectTagger::tag('Invoice is wrong', 'TKT-000042');

        /*
         * The load-bearing case. A long thread would otherwise accumulate
         * `[#TKT-000042] Re: [#TKT-000042] Re: …` until the tag was longer than
         * the subject.
         */
        $this->assertSame($once, SubjectTagger::tag($once, 'TKT-000042'));
    }

    public function test_it_survives_a_reply_prefix(): void
    {
        $tagged = SubjectTagger::tag('Invoice is wrong', 'TKT-000042');

        // What actually comes back from a mail client.
        $this->assertSame(
            "Re: {$tagged}",
            SubjectTagger::tag("Re: {$tagged}", 'TKT-000042'),
        );
    }

    public function test_it_survives_several_reply_prefixes(): void
    {
        $tagged = SubjectTagger::tag('Invoice is wrong', 'TKT-000042');
        $replied = "Re: Fwd: Re: {$tagged}";

        $this->assertSame($replied, SubjectTagger::tag($replied, 'TKT-000042'));
    }

    public function test_a_tag_for_another_ticket_is_replaced_not_appended(): void
    {
        $forwarded = '[#TKT-000001] Invoice is wrong';

        /*
         * Two references in one subject would make Story 5.2 guess which ticket
         * the customer meant — and it would guess wrong roughly half the time.
         */
        $result = SubjectTagger::tag($forwarded, 'TKT-000042');

        $this->assertStringContainsString('TKT-000042', $result);
        $this->assertStringNotContainsString('TKT-000001', $result);
    }

    public function test_an_empty_subject_becomes_just_the_tag(): void
    {
        // Better than "[#TKT-000042] " with a trailing space, which some
        // clients render as an empty-looking subject.
        $this->assertSame('[#TKT-000042]', SubjectTagger::tag('', 'TKT-000042'));
    }

    public function test_it_reads_a_reference_back_out(): void
    {
        // The other half of the round trip: what Story 5.2 will call.
        $this->assertSame(
            'TKT-000042',
            SubjectTagger::referenceIn('Re: [#TKT-000042] Invoice is wrong'),
        );
    }

    public function test_a_subject_with_no_tag_reads_as_none(): void
    {
        $this->assertNull(SubjectTagger::referenceIn('Just an email'));
    }

    public function test_something_that_merely_looks_like_a_tag_is_not_one(): void
    {
        // Brackets are common in subjects; only the `#REF-0000` shape counts.
        $this->assertNull(SubjectTagger::referenceIn('[URGENT] Invoice is wrong'));
        $this->assertNull(SubjectTagger::referenceIn('[#notaref] Hello'));
    }

    public function test_an_arabic_subject_is_tagged_without_being_mangled(): void
    {
        $arabic = 'الفاتورة فيها رسوم مكرّرة';

        $result = SubjectTagger::tag($arabic, 'TKT-000042');

        // Byte-identical. Half the subjects in this system are Arabic, and a
        // tagger that corrupted them would corrupt half the mail.
        $this->assertStringContainsString($arabic, $result);
        $this->assertSame('[#TKT-000042] '.$arabic, $result);
    }
}
