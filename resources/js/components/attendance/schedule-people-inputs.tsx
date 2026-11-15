import type { SchedulePerson } from '@/types';

/** What names the people of a schedule for several: their ids, sent with it (one person goes in the address). */
export default function SchedulePeopleInputs({
    people,
}: {
    people: SchedulePerson[];
}) {
    return people.length > 1
        ? people.map((person) => (
              <input
                  key={person.id}
                  type="hidden"
                  name="employee_ids[]"
                  value={person.id}
              />
          ))
        : null;
}
